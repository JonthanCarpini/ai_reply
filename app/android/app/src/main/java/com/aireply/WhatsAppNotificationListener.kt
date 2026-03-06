package com.aireply

import android.app.Notification
import android.content.Intent
import android.os.Bundle
import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import android.util.Log
import androidx.core.app.RemoteInput
import kotlinx.coroutines.*
import org.json.JSONObject
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL

class WhatsAppNotificationListener : NotificationListenerService() {

    companion object {
        private const val TAG = "AIReplyListener"
        private val WHATSAPP_PACKAGES = setOf("com.whatsapp", "com.whatsapp.w4b")
        var isRunning = false
            private set
    }

    private val scope = CoroutineScope(Dispatchers.IO + SupervisorJob())

    override fun onListenerConnected() {
        super.onListenerConnected()
        isRunning = true
        Log.i(TAG, "NotificationListener connected")
        NotificationBridge.sendServiceStatus(this, true)
    }

    override fun onListenerDisconnected() {
        super.onListenerDisconnected()
        isRunning = false
        Log.i(TAG, "NotificationListener disconnected")
        NotificationBridge.sendServiceStatus(this, false)
    }

    override fun onNotificationPosted(sbn: StatusBarNotification) {
        if (sbn.packageName !in WHATSAPP_PACKAGES) return

        val extras = sbn.notification.extras ?: return
        val title = extras.getString(Notification.EXTRA_TITLE) ?: return
        val text = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString() ?: return

        // Ignorar grupos (mensagens com ":" no texto indicam grupo)
        if (text.contains(":") && !title.contains("+")) return

        // Ignorar mensagens vazias ou muito curtas
        if (text.isBlank() || text.length < 2) return

        // Encontrar Reply Action
        val replyAction = sbn.notification.actions?.find { action ->
            action.remoteInputs?.isNotEmpty() == true
        } ?: return

        Log.d(TAG, "Message from $title: $text")

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

        // Extrair telefone do contato (se disponível na notificação)
        val phone = extractPhone(sbn)

        // Chamar API do backend
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
            put("source", "whatsapp")
        }

        OutputStreamWriter(conn.outputStream).use { it.write(body.toString()) }

        val responseCode = conn.responseCode
        if (responseCode != 200) {
            val errorBody = conn.errorStream?.bufferedReader()?.readText() ?: "No error body"
            Log.e(TAG, "API error $responseCode: $errorBody")
            NotificationBridge.sendError(this, "API error: $responseCode")
            return
        }

        val responseBody = conn.inputStream.bufferedReader().readText()
        val json = JSONObject(responseBody)
        val reply = json.optString("reply", "")

        NotificationBridge.sendMessageProcessed(this, sender, phone, message, reply)

        // Responder via Reply Action
        if (reply.isNotEmpty()) {
            val intent = Intent()
            val bundle = Bundle()
            replyAction.remoteInputs?.forEach { remoteInput ->
                bundle.putCharSequence(remoteInput.resultKey, reply)
            }
            RemoteInput.addResultsToIntent(replyAction.remoteInputs, intent, bundle)
            replyAction.actionIntent.send(this, 0, intent)

            // Cancelar notificação para limpar
            cancelNotification(sbn.key)
            Log.i(TAG, "Replied to $sender: ${reply.take(50)}...")
        }
    }

    private fun extractPhone(sbn: StatusBarNotification): String {
        // Tentar extrair telefone do key ou tag da notificação
        val key = sbn.key ?: ""
        val phoneRegex = Regex("\\+?\\d{10,15}")
        return phoneRegex.find(key)?.value ?: ""
    }

    override fun onDestroy() {
        scope.cancel()
        isRunning = false
        super.onDestroy()
    }
}
