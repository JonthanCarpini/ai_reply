package com.aireply

import android.app.Notification
import android.content.Intent
import android.os.Bundle
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import android.util.Log
import android.app.RemoteInput
import kotlinx.coroutines.*
import org.json.JSONObject
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.ConcurrentHashMap

class WhatsAppNotificationListener : NotificationListenerService() {

    companion object {
        private const val TAG = "AIReplyListener"
        private val DEFAULT_PACKAGES = setOf("com.whatsapp", "com.whatsapp.w4b")
        private const val POST_REPLY_BLOCK_MS = 5_000L
        var isRunning = false
            private set
    }

    private val scope = CoroutineScope(Dispatchers.IO + SupervisorJob())

    // Contato → flag atômico de processamento ativo
    private val isProcessing = ConcurrentHashMap<String, Boolean>()

    // Contato → timestamp da última mensagem processada (evita reprocessar)
    private val lastProcessedTimestamp = ConcurrentHashMap<String, Long>()

    private fun getAllowedPackages(): Set<String> {
        val prefs = getSharedPreferences("ai_reply_prefs", MODE_PRIVATE)
        return prefs.getStringSet("whatsapp_packages", DEFAULT_PACKAGES) ?: DEFAULT_PACKAGES
    }

    override fun onListenerConnected() {
        super.onListenerConnected()
        isRunning = true
        Log.i(TAG, "NotificationListener connected — monitoring: ${getAllowedPackages()}")
        NotificationBridge.sendServiceStatus(this, true)
    }

    override fun onListenerDisconnected() {
        super.onListenerDisconnected()
        isRunning = false
        Log.i(TAG, "NotificationListener disconnected")
        NotificationBridge.sendServiceStatus(this, false)
    }

    override fun onNotificationPosted(sbn: StatusBarNotification) {
        val allowedPackages = getAllowedPackages()
        if (sbn.packageName !in allowedPackages) return

        val extras = sbn.notification.extras ?: return
        val title = extras.getString(Notification.EXTRA_TITLE) ?: return
        val flags = sbn.notification.flags

        // Ignorar summary/agrupadas
        if (flags and Notification.FLAG_GROUP_SUMMARY != 0) return

        // Ignorar grupo
        if (extras.getBoolean(Notification.EXTRA_IS_GROUP_CONVERSATION, false)) return

        // ===== EXTRAIR MENSAGEM VIA MessagingStyle (EXTRA_MESSAGES) =====
        var messageText: String? = null
        var messageTime: Long = 0
        var isFromContact = false

        @Suppress("DEPRECATION")
        val msgArray = extras.getParcelableArray(Notification.EXTRA_MESSAGES)
        if (msgArray != null && msgArray.isNotEmpty()) {
            val lastMsg = msgArray.last() as? Bundle
            if (lastMsg != null) {
                val sender = lastMsg.getCharSequence("sender")
                messageText = lastMsg.getCharSequence("text")?.toString()
                messageTime = lastMsg.getLong("time", 0L)
                // sender == null → mensagem enviada por MIM (bot) → ignorar
                // sender != null → mensagem do CONTATO → processar
                isFromContact = sender != null
                Log.d(TAG, ">>> MSG_STYLE: title='$title' sender='$sender' text='${messageText?.take(50)}' time=$messageTime from_contact=$isFromContact")
            }
        }

        // Fallback: se EXTRA_MESSAGES não disponível, usar EXTRA_TEXT
        if (messageText == null) {
            messageText = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString()
            messageTime = sbn.postTime
            // No fallback, assumir que é do contato MAS verificar via isProcessing
            isFromContact = true
            Log.d(TAG, ">>> FALLBACK: title='$title' text='${messageText?.take(50)}' time=$messageTime")
        }

        if (messageText.isNullOrBlank() || messageText.length < 2) return

        // ===== FILTRO 1: Ignorar mensagens "de mim" (respostas do bot) =====
        if (!isFromContact) {
            Log.d(TAG, "SKIP: from_me (bot reply) for $title")
            return
        }

        // ===== FILTRO 2: Verificar se já processamos esta mensagem pelo timestamp =====
        if (messageTime > 0) {
            val lastProcessed = lastProcessedTimestamp[title] ?: 0L
            if (messageTime <= lastProcessed) {
                Log.d(TAG, "SKIP: already processed ts=$messageTime for $title")
                return
            }
        }

        // ===== FILTRO 3: Bloqueio atômico — evitar processamentos paralelos =====
        if (isProcessing.putIfAbsent(title, true) != null) {
            Log.d(TAG, "SKIP: already processing for $title")
            return
        }

        // Registrar timestamp processado
        if (messageTime > 0) {
            lastProcessedTimestamp[title] = messageTime
        }

        // Verificar reply action
        val replyAction = sbn.notification.actions?.find { action ->
            action.remoteInputs?.isNotEmpty() == true
        }
        if (replyAction == null) {
            isProcessing.remove(title)
            return
        }

        Log.i(TAG, "=== ACCEPTED: $title msg='${messageText.take(50)}' ts=$messageTime")

        scope.launch {
            try {
                processMessage(title, messageText, replyAction, sbn)
            } catch (e: Exception) {
                Log.e(TAG, "Error processing message", e)
                NotificationBridge.sendError(this@WhatsAppNotificationListener, e.message ?: "Unknown error")
            } finally {
                // Manter bloqueio por POST_REPLY_BLOCK_MS para absorver notificações pós-envio
                delay(POST_REPLY_BLOCK_MS)
                isProcessing.remove(title)
                Log.d(TAG, "=== UNLOCKED: $title")
            }
        }
    }

    private suspend fun processMessage(
        sender: String,
        message: String,
        replyAction: Notification.Action,
        sbn: StatusBarNotification
    ) {
        val prefs = getSharedPreferences("ai_reply_prefs", MODE_PRIVATE)
        val token = prefs.getString("auth_token", null) ?: run {
            Log.w(TAG, "No auth token set")
            return
        }
        val apiUrl = prefs.getString("api_url", "https://api.aireply.xpainel.online/api") ?: return

        val phone = extractPhone(sbn).ifEmpty { sender }
        Log.d(TAG, "Sending to API: phone=$phone sender=$sender msg=${message.take(30)}")

        val url = URL("$apiUrl/messages/process")
        val conn = url.openConnection() as HttpURLConnection
        conn.requestMethod = "POST"
        conn.setRequestProperty("Content-Type", "application/json")
        conn.setRequestProperty("Accept", "application/json")
        conn.setRequestProperty("Authorization", "Bearer $token")
        conn.doOutput = true
        conn.connectTimeout = 30000
        conn.readTimeout = 30000

        val body = JSONObject().apply {
            put("contact_name", sender)
            put("contact_phone", phone)
            put("message", message)
        }

        OutputStreamWriter(conn.outputStream).use { it.write(body.toString()) }

        val responseCode = conn.responseCode
        if (responseCode != 200) {
            val errorBody = conn.errorStream?.bufferedReader()?.readText() ?: "No error body"
            Log.e(TAG, "API error $responseCode: $errorBody")
            NotificationBridge.sendError(this, "API error: $responseCode - $errorBody")
            return
        }

        val responseBody = conn.inputStream.bufferedReader().readText()
        val json = JSONObject(responseBody)
        val reply = json.optString("reply", "")
        val error = json.optString("error", "")
        val blocked = json.optBoolean("blocked", false)

        // Notificar o app sobre a mensagem processada
        NotificationBridge.sendMessageProcessed(this, sender, phone, message, reply)

        // NUNCA enviar mensagens de erro/limite/bloqueio ao cliente
        if (error.isNotEmpty()) {
            Log.w(TAG, "API returned error flag: $error — NOT replying to client")
            NotificationBridge.sendError(this, error)
            return
        }
        if (blocked) {
            Log.d(TAG, "Message blocked by rules — NOT replying to client")
            return
        }
        if (reply.isEmpty()) {
            Log.d(TAG, "Empty reply — NOT replying to client")
            return
        }

        // Enviar resposta via WhatsApp
        val intent = Intent()
        val bundle = Bundle()
        replyAction.remoteInputs?.forEach { remoteInput ->
            bundle.putCharSequence(remoteInput.resultKey, reply)
        }
        RemoteInput.addResultsToIntent(replyAction.remoteInputs, intent, bundle)
        replyAction.actionIntent.send(this, 0, intent)

        cancelNotification(sbn.key)
        Log.i(TAG, "Replied to $sender: ${reply.take(50)}...")

        // Limpar entradas antigas periodicamente
        scope.launch {
            delay(120_000)
            cleanupOldEntries()
        }
    }

    private fun extractPhone(sbn: StatusBarNotification): String {
        val phoneRegex = Regex("\\+?\\d{10,15}")

        val key = sbn.key ?: ""
        phoneRegex.find(key)?.value?.let { return it }

        sbn.tag?.let { tag ->
            phoneRegex.find(tag)?.value?.let { return it }
        }

        val extras = sbn.notification.extras
        val convTitle = extras?.getString("android.conversationTitle") ?: ""
        phoneRegex.find(convTitle)?.value?.let { return it }

        val title = extras?.getString(Notification.EXTRA_TITLE) ?: ""
        phoneRegex.find(title)?.value?.let { return it }

        return ""
    }

    private fun cleanupOldEntries() {
        val cutoff = System.currentTimeMillis() - 300_000L
        lastProcessedTimestamp.entries.removeAll { it.value < cutoff }
    }

    override fun onDestroy() {
        scope.cancel()
        isRunning = false
        super.onDestroy()
    }
}
