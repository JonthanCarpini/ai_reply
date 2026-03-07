package com.aireply

import android.app.Notification
import android.content.Intent
import android.os.Bundle
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import android.util.Log
import android.app.RemoteInput
import kotlinx.coroutines.*
import kotlinx.coroutines.sync.Mutex
import org.json.JSONObject
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.ConcurrentHashMap

class WhatsAppNotificationListener : NotificationListenerService() {

    companion object {
        private const val TAG = "AIReplyListener"
        private val DEFAULT_PACKAGES = setOf("com.whatsapp", "com.whatsapp.w4b")
        private const val COOLDOWN_MS = 30_000L
        private const val DEDUP_WINDOW_MS = 5_000L
        private const val POST_REPLY_BLOCK_MS = 4_000L
        var isRunning = false
            private set
    }

    private val scope = CoroutineScope(Dispatchers.IO + SupervisorJob())

    // Cooldown: contato → timestamp da última resposta enviada
    private val lastReplyTime = ConcurrentHashMap<String, Long>()

    // Timestamp do último envio de resposta por contato (para bloquear notificações próprias)
    private val lastSentTime = ConcurrentHashMap<String, Long>()

    // Mutex por contato: evita processar 2 mensagens do mesmo contato em paralelo
    private val contactMutex = ConcurrentHashMap<String, Mutex>()

    // Dedup: chave(contato+msg) → timestamp — evita processar mesma msg 2x
    private val recentMessages = ConcurrentHashMap<String, Long>()

    // Contato → último sbn.key processado (para ignorar re-posts da mesma notificação)
    private val lastNotificationKey = ConcurrentHashMap<String, String>()

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
        val text = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString() ?: return

        if (text.isBlank() || text.length < 2) return

        // Ignorar notificações de summary/agrupadas (ex: "2 novas mensagens")
        if (sbn.notification.flags and Notification.FLAG_GROUP_SUMMARY != 0) {
            Log.d(TAG, "SKIP: group summary notification")
            return
        }

        // Ignorar mensagens de grupo
        val isGroupMessage = extras.getBoolean(Notification.EXTRA_IS_GROUP_CONVERSATION, false)
        if (isGroupMessage) {
            Log.d(TAG, "SKIP: group message from $title")
            return
        }

        // Ignorar notificações que chegam logo após enviarmos uma resposta (são nossas próprias respostas)
        val now = System.currentTimeMillis()
        val lastSent = lastSentTime[title] ?: 0L
        if (now - lastSent < POST_REPLY_BLOCK_MS) {
            Log.d(TAG, "SKIP: post-reply block active for $title (${now - lastSent}ms after send)")
            return
        }

        // Ignorar re-post da mesma notificação (WhatsApp atualiza notificações existentes)
        val nKey = sbn.key + ":" + text.hashCode()
        val prevKey = lastNotificationKey.put(title, nKey)
        if (prevKey == nKey) {
            Log.d(TAG, "SKIP: same notification re-posted for $title")
            return
        }

        // Dedup: ignorar mensagem idêntica do mesmo contato dentro de DEDUP_WINDOW_MS
        val dedupKey = "${title}:${text.trim().lowercase().take(100)}"
        val lastSeen = recentMessages.put(dedupKey, now)
        if (lastSeen != null && (now - lastSeen) < DEDUP_WINDOW_MS) {
            Log.d(TAG, "SKIP: duplicate message from $title within ${DEDUP_WINDOW_MS}ms")
            return
        }

        // Cooldown: ignorar mensagens do mesmo contato dentro de COOLDOWN_MS
        val lastReply = lastReplyTime[title] ?: 0L
        if (now - lastReply < COOLDOWN_MS) {
            Log.d(TAG, "SKIP: cooldown active for $title (${(now - lastReply) / 1000}s ago)")
            return
        }

        // Marcar cooldown IMEDIATAMENTE para evitar race condition
        lastReplyTime[title] = now

        // Verificar se tem ação de reply disponível
        val replyAction = sbn.notification.actions?.find { action ->
            action.remoteInputs?.isNotEmpty() == true
        }
        if (replyAction == null) {
            // Reverter cooldown se não há como responder
            lastReplyTime[title] = lastReply
            return
        }

        Log.i(TAG, "Processing message from $title: ${text.take(50)}")

        // Obter ou criar mutex para este contato
        val mutex = contactMutex.getOrPut(title) { Mutex() }

        scope.launch {
            mutex.lock()
            try {
                processMessage(title, text, replyAction, sbn)
            } catch (e: Exception) {
                Log.e(TAG, "Error processing message", e)
                NotificationBridge.sendError(this@WhatsAppNotificationListener, e.message ?: "Unknown error")
            } finally {
                mutex.unlock()
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

        // Marcar timestamp de envio ANTES de enviar (bloqueia notificações próprias)
        lastSentTime[sender] = System.currentTimeMillis()

        // Atualizar cooldown com timestamp real do envio
        lastReplyTime[sender] = System.currentTimeMillis()

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

        // Limpar entradas antigas após 120s
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
        val cutoff = System.currentTimeMillis() - 120_000L
        recentMessages.entries.removeAll { it.value < cutoff }
        lastReplyTime.entries.removeAll { it.value < cutoff }
        lastSentTime.entries.removeAll { it.value < cutoff }
        // Remover mutexes de contatos inativos e keys antigos
        contactMutex.entries.removeAll { !it.value.isLocked }
        // lastNotificationKey não precisa de limpeza agressiva (1 entry por contato)
    }

    override fun onDestroy() {
        scope.cancel()
        isRunning = false
        super.onDestroy()
    }
}
