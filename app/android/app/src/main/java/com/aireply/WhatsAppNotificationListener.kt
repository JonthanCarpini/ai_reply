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

class WhatsAppNotificationListener : NotificationListenerService() {

    companion object {
        private const val TAG = "AIReplyListener"
        private val DEFAULT_PACKAGES = setOf("com.whatsapp", "com.whatsapp.w4b")
        private const val COOLDOWN_MS = 30_000L
        var isRunning = false
            private set
    }

    private val scope = CoroutineScope(Dispatchers.IO + SupervisorJob())

    // Cooldown: contato → timestamp da última resposta enviada
    private val lastReplyTime = mutableMapOf<String, Long>()

    // Mensagens enviadas pelo próprio bot (para ignorar notificações de respostas próprias)
    private val sentReplies = mutableSetOf<String>()

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

        // Ignorar mensagens de grupo
        val isGroupMessage = extras.getBoolean(Notification.EXTRA_IS_GROUP_CONVERSATION, false)
        if (isGroupMessage) {
            Log.d(TAG, "SKIP: group message from $title")
            return
        }

        // Ignorar se é uma resposta que nós mesmos enviamos
        val textKey = "${title}:${text.take(80)}"
        if (sentReplies.remove(textKey)) {
            Log.d(TAG, "SKIP: own reply to $title")
            return
        }

        // Cooldown: ignorar mensagens do mesmo contato dentro de COOLDOWN_MS
        val now = System.currentTimeMillis()
        val lastReply = lastReplyTime[title] ?: 0L
        if (now - lastReply < COOLDOWN_MS) {
            Log.d(TAG, "SKIP: cooldown active for $title (${(now - lastReply) / 1000}s ago)")
            return
        }

        // Verificar se tem ação de reply disponível
        val replyAction = sbn.notification.actions?.find { action ->
            action.remoteInputs?.isNotEmpty() == true
        } ?: return

        Log.i(TAG, "Processing message from $title: ${text.take(50)}")

        scope.launch {
            try {
                processMessage(title, text, replyAction, sbn)
            } catch (e: Exception) {
                Log.e(TAG, "Error processing message", e)
                NotificationBridge.sendError(this@WhatsAppNotificationListener, e.message ?: "Unknown error")
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

        // Registrar resposta antes de enviar (para ignorar a notificação de retorno)
        val replyKey = "${sender}:${reply.take(80)}"
        sentReplies.add(replyKey)

        // Marcar cooldown para este contato
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

        // Limpar sentReplies antigos após 10s
        scope.launch {
            delay(10_000)
            sentReplies.remove(replyKey)
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

    override fun onDestroy() {
        scope.cancel()
        isRunning = false
        super.onDestroy()
    }
}
