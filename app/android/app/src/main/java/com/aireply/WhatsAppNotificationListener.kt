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
import org.json.JSONArray
import java.io.OutputStreamWriter
import java.net.HttpURLConnection
import java.net.URL
import java.util.concurrent.ConcurrentHashMap
import java.util.concurrent.CopyOnWriteArrayList

class WhatsAppNotificationListener : NotificationListenerService() {

    companion object {
        private const val TAG = "AIReplyListener"
        private val DEFAULT_PACKAGES = setOf("com.whatsapp", "com.whatsapp.w4b")
        private const val BUFFER_DELAY_MS = 3_000L
        private const val POST_REPLY_BLOCK_MS = 10_000L
        var isRunning = false
            private set
    }

    private val scope = CoroutineScope(Dispatchers.IO + SupervisorJob())

    data class BufferedMessage(
        val text: String,
        val time: Long,
        val fromMe: Boolean,
        val sbn: StatusBarNotification
    )

    private val messageBuffer = ConcurrentHashMap<String, CopyOnWriteArrayList<BufferedMessage>>()
    private val bufferTimers = ConcurrentHashMap<String, Job>()
    private val isProcessing = ConcurrentHashMap<String, Boolean>()

    private fun getAllowedPackages(): Set<String> {
        val prefs = getSharedPreferences("ai_reply_prefs", MODE_PRIVATE)
        return prefs.getStringSet("whatsapp_packages", DEFAULT_PACKAGES) ?: DEFAULT_PACKAGES
    }

    private fun getAuthToken(): String? =
        getSharedPreferences("ai_reply_prefs", MODE_PRIVATE).getString("auth_token", null)

    private fun getApiUrl(): String =
        getSharedPreferences("ai_reply_prefs", MODE_PRIVATE)
            .getString("api_url", "https://api.aireply.xpainel.online/api")
            ?: "https://api.aireply.xpainel.online/api"

    override fun onListenerConnected() {
        super.onListenerConnected()
        isRunning = true
        Log.i(TAG, "v2.0 connected — monitoring: ${getAllowedPackages()}")
        NotificationBridge.sendServiceStatus(this, true)
    }

    override fun onListenerDisconnected() {
        super.onListenerDisconnected()
        isRunning = false
        NotificationBridge.sendServiceStatus(this, false)
    }

    override fun onNotificationPosted(sbn: StatusBarNotification) {
        if (sbn.packageName !in getAllowedPackages()) return

        val extras = sbn.notification.extras ?: return
        val title = extras.getString(Notification.EXTRA_TITLE) ?: return

        if (sbn.notification.flags and Notification.FLAG_GROUP_SUMMARY != 0) return
        if (extras.getBoolean(Notification.EXTRA_IS_GROUP_CONVERSATION, false)) return

        // ── Extrair mensagem via EXTRA_MESSAGES ──
        var messageText: String? = null
        var messageTime: Long = 0
        var fromMe = false

        @Suppress("DEPRECATION")
        val msgArray = extras.getParcelableArray(Notification.EXTRA_MESSAGES)
        if (msgArray != null && msgArray.isNotEmpty()) {
            val lastMsg = msgArray.last() as? Bundle
            if (lastMsg != null) {
                val sender = lastMsg.getCharSequence("sender")
                messageText = lastMsg.getCharSequence("text")?.toString()
                messageTime = lastMsg.getLong("time", 0L)
                fromMe = sender == null
            }
        }

        if (messageText == null) {
            messageText = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString()
            messageTime = sbn.postTime
        }

        if (messageText.isNullOrBlank() || messageText.length < 2) return

        // ── Log remoto (fire-and-forget) ──
        sendRemoteLog(title, messageText, messageTime, fromMe, sbn)

        // ── Se em processamento, ignorar (evita race condition) ──
        if (isProcessing[title] == true) {
            Log.d(TAG, "SKIP: processing $title")
            return
        }

        // ── Adicionar ao buffer ──
        val msg = BufferedMessage(messageText, messageTime, fromMe, sbn)
        messageBuffer.getOrPut(title) { CopyOnWriteArrayList() }.add(msg)

        // ── Resetar timer ──
        bufferTimers[title]?.cancel()
        bufferTimers[title] = scope.launch {
            delay(BUFFER_DELAY_MS)
            flushBuffer(title)
        }
    }

    private suspend fun flushBuffer(contact: String) {
        val messages = messageBuffer.remove(contact) ?: return
        bufferTimers.remove(contact)

        if (messages.isEmpty()) return

        // Pegar a última SBN com reply action
        val lastSbn = messages.last().sbn
        val replyAction = lastSbn.notification.actions?.find {
            it.remoteInputs?.isNotEmpty() == true
        }
        if (replyAction == null) {
            Log.d(TAG, "SKIP: no reply action for $contact")
            return
        }

        if (isProcessing.putIfAbsent(contact, true) != null) {
            Log.d(TAG, "SKIP: already processing $contact")
            return
        }

        try {
            // Enviar TODAS as mensagens concatenadas + from_me da última
            // O backend decide se processa ou ignora
            val fullMessage = messages.joinToString("\n") { it.text }
            val lastFromMe = messages.last().fromMe

            Log.i(TAG, "=== SEND: $contact msgs=${messages.size} from_me=$lastFromMe text='${fullMessage.take(80)}'")

            callApiAndReply(contact, fullMessage, lastFromMe, replyAction, lastSbn)
        } catch (e: Exception) {
            Log.e(TAG, "Error in flushBuffer", e)
            NotificationBridge.sendError(this@WhatsAppNotificationListener, e.message ?: "Error")
        } finally {
            delay(POST_REPLY_BLOCK_MS)
            isProcessing.remove(contact)
            Log.d(TAG, "=== UNLOCKED: $contact")
        }
    }

    private suspend fun callApiAndReply(
        sender: String,
        message: String,
        fromMe: Boolean,
        replyAction: Notification.Action,
        sbn: StatusBarNotification
    ) {
        val token = getAuthToken() ?: return
        val apiUrl = getApiUrl()
        val phone = extractPhone(sbn).ifEmpty { sender }

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
            put("from_me", fromMe)
        }

        OutputStreamWriter(conn.outputStream).use { it.write(body.toString()) }

        val responseCode = conn.responseCode
        if (responseCode != 200) {
            val err = conn.errorStream?.bufferedReader()?.readText() ?: ""
            Log.e(TAG, "API $responseCode: $err")
            NotificationBridge.sendError(this, "API $responseCode")
            return
        }

        val responseBody = conn.inputStream.bufferedReader().readText()
        val json = JSONObject(responseBody)
        val reply = json.optString("reply", "")
        val skipped = json.optBoolean("skipped", false)

        Log.d(TAG, "API_RESP: reply='${reply.take(60)}' skipped=$skipped")

        NotificationBridge.sendMessageProcessed(this, sender, phone, message, reply)

        // Se backend disse para ignorar ou resposta vazia → não responder
        if (skipped || reply.isEmpty()) {
            Log.d(TAG, "NO_REPLY: skipped=$skipped empty=${reply.isEmpty()}")
            return
        }

        if (json.optString("error", "").isNotEmpty()) {
            Log.w(TAG, "API error: ${json.optString("error")}")
            return
        }
        if (json.optBoolean("blocked", false)) {
            Log.d(TAG, "Blocked by rules")
            return
        }

        // ── Enviar resposta via WhatsApp ──
        val intent = Intent()
        val bundle = Bundle()
        replyAction.remoteInputs?.forEach { ri ->
            bundle.putCharSequence(ri.resultKey, reply)
        }
        RemoteInput.addResultsToIntent(replyAction.remoteInputs, intent, bundle)
        replyAction.actionIntent.send(this, 0, intent)

        Log.i(TAG, "REPLIED: $sender → '${reply.take(60)}'")
    }

    private fun sendRemoteLog(
        contact: String, text: String, time: Long, fromMe: Boolean,
        sbn: StatusBarNotification
    ) {
        scope.launch {
            try {
                val token = getAuthToken() ?: return@launch
                val apiUrl = getApiUrl()

                val conn = URL("$apiUrl/messages/notification-log")
                    .openConnection() as HttpURLConnection
                conn.requestMethod = "POST"
                conn.setRequestProperty("Content-Type", "application/json")
                conn.setRequestProperty("Authorization", "Bearer $token")
                conn.doOutput = true
                conn.connectTimeout = 5000
                conn.readTimeout = 5000

                val body = JSONObject().apply {
                    put("event", "notification_received")
                    put("contact", contact)
                    put("text", text.take(200))
                    put("time", time)
                    put("from_me", fromMe)
                    put("is_processing", isProcessing[contact] == true)
                    put("buffer_size", messageBuffer[contact]?.size ?: 0)
                    put("app_version", "2.0")
                    put("ts", System.currentTimeMillis())
                }

                OutputStreamWriter(conn.outputStream).use { it.write(body.toString()) }
                conn.responseCode
                conn.disconnect()
            } catch (_: Exception) {}
        }
    }

    private fun extractPhone(sbn: StatusBarNotification): String {
        val regex = Regex("\\+?\\d{10,15}")
        regex.find(sbn.key ?: "")?.value?.let { return it }
        sbn.tag?.let { regex.find(it)?.value?.let { v -> return v } }
        val extras = sbn.notification.extras
        regex.find(extras?.getString("android.conversationTitle") ?: "")?.value?.let { return it }
        regex.find(extras?.getString(Notification.EXTRA_TITLE) ?: "")?.value?.let { return it }
        return ""
    }

    override fun onDestroy() {
        scope.cancel()
        isRunning = false
        super.onDestroy()
    }
}
