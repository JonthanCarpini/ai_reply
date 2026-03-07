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
        private const val POST_REPLY_BLOCK_MS = 5_000L
        var isRunning = false
            private set
    }

    private val scope = CoroutineScope(Dispatchers.IO + SupervisorJob())

    // ── Buffer de mensagens por contato ──
    data class BufferedNotification(
        val text: String,
        val time: Long,
        val isFromMe: Boolean,
        val senderName: String?,
        val sbn: StatusBarNotification
    )

    private val messageBuffer = ConcurrentHashMap<String, CopyOnWriteArrayList<BufferedNotification>>()
    private val bufferTimers = ConcurrentHashMap<String, Job>()

    // ── Controle de processamento ──
    private val isProcessing = ConcurrentHashMap<String, Boolean>()

    // ── Cache de respostas enviadas (fallback from_me) ──
    private val sentRepliesCache = ConcurrentHashMap<String, Long>()

    private fun getAllowedPackages(): Set<String> {
        val prefs = getSharedPreferences("ai_reply_prefs", MODE_PRIVATE)
        return prefs.getStringSet("whatsapp_packages", DEFAULT_PACKAGES) ?: DEFAULT_PACKAGES
    }

    private fun getAuthToken(): String? =
        getSharedPreferences("ai_reply_prefs", MODE_PRIVATE).getString("auth_token", null)

    private fun getApiUrl(): String =
        getSharedPreferences("ai_reply_prefs", MODE_PRIVATE)
            .getString("api_url", "https://api.aireply.xpainel.online/api") ?: "https://api.aireply.xpainel.online/api"

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

    // ══════════════════════════════════════════════════════════
    //  PONTO DE ENTRADA: cada notificação do WhatsApp chega aqui
    // ══════════════════════════════════════════════════════════
    override fun onNotificationPosted(sbn: StatusBarNotification) {
        if (sbn.packageName !in getAllowedPackages()) return

        val extras = sbn.notification.extras ?: return
        val title = extras.getString(Notification.EXTRA_TITLE) ?: return
        val flags = sbn.notification.flags

        // Ignorar summary/agrupadas
        if (flags and Notification.FLAG_GROUP_SUMMARY != 0) return
        // Ignorar grupo
        if (extras.getBoolean(Notification.EXTRA_IS_GROUP_CONVERSATION, false)) return

        // ── Extrair dados da notificação ──
        var messageText: String? = null
        var messageTime: Long = 0
        var isFromMe = false
        var senderField: String? = null
        var allMessagesJson = ""

        @Suppress("DEPRECATION")
        val msgArray = extras.getParcelableArray(Notification.EXTRA_MESSAGES)
        if (msgArray != null && msgArray.isNotEmpty()) {
            // Logar TODAS as mensagens do array para debug
            val jsonArr = JSONArray()
            for (parcel in msgArray) {
                val b = parcel as? Bundle ?: continue
                val obj = JSONObject()
                obj.put("sender", b.getCharSequence("sender")?.toString() ?: "null")
                obj.put("text", b.getCharSequence("text")?.toString()?.take(80) ?: "")
                obj.put("time", b.getLong("time", 0))
                jsonArr.put(obj)
            }
            allMessagesJson = jsonArr.toString()

            val lastMsg = msgArray.last() as? Bundle
            if (lastMsg != null) {
                senderField = lastMsg.getCharSequence("sender")?.toString()
                messageText = lastMsg.getCharSequence("text")?.toString()
                messageTime = lastMsg.getLong("time", 0L)
                // sender == null → mensagem do dono do dispositivo (bot/eu)
                isFromMe = senderField == null
            }
        }

        // Fallback: EXTRA_TEXT
        if (messageText == null) {
            messageText = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString()
            messageTime = sbn.postTime
            isFromMe = false // sem info, assumir que é do contato
        }

        if (messageText.isNullOrBlank() || messageText.length < 2) return

        // Fallback from_me: comparar com cache de respostas enviadas
        if (!isFromMe) {
            val cacheKey = "${title}:${messageText.trim().take(80).lowercase()}"
            val cachedTime = sentRepliesCache[cacheKey]
            if (cachedTime != null && (System.currentTimeMillis() - cachedTime) < 30_000) {
                isFromMe = true
                Log.d(TAG, "FROM_ME detected via sentRepliesCache for $title")
            }
        }

        val extraText = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString() ?: ""

        Log.d(TAG, ">>> NOTIF: title='$title' text='${messageText.take(60)}' sender='$senderField' from_me=$isFromMe time=$messageTime pkg=${sbn.packageName}")

        // ── Enviar LOG REMOTO para o backend (fire-and-forget) ──
        sendRemoteLog(
            title, messageText, messageTime, isFromMe, senderField,
            sbn.key, sbn.packageName, flags, extraText, allMessagesJson
        )

        // ── Se estamos processando este contato, ignorar ──
        if (isProcessing[title] == true) {
            Log.d(TAG, "SKIP: currently processing $title")
            return
        }

        // ── Adicionar ao buffer do contato ──
        val buffered = BufferedNotification(messageText, messageTime, isFromMe, senderField, sbn)
        messageBuffer.getOrPut(title) { CopyOnWriteArrayList() }.add(buffered)

        // ── Resetar timer de BUFFER_DELAY_MS ──
        bufferTimers[title]?.cancel()
        bufferTimers[title] = scope.launch {
            delay(BUFFER_DELAY_MS)
            processBufferedMessages(title)
        }
    }

    // ══════════════════════════════════════════════════════════
    //  PROCESSAR BUFFER: chamado após BUFFER_DELAY_MS sem novas notificações
    // ══════════════════════════════════════════════════════════
    private suspend fun processBufferedMessages(contact: String) {
        val messages = messageBuffer.remove(contact) ?: return
        bufferTimers.remove(contact)

        // Filtrar apenas mensagens do contato (NÃO from_me)
        val contactMessages = messages.filter { !it.isFromMe }

        Log.d(TAG, "BUFFER[$contact]: total=${messages.size} from_contact=${contactMessages.size} from_me=${messages.size - contactMessages.size}")

        if (contactMessages.isEmpty()) {
            Log.d(TAG, "SKIP: all messages were from_me for $contact")
            return
        }

        // Bloqueio atômico
        if (isProcessing.putIfAbsent(contact, true) != null) {
            Log.d(TAG, "SKIP: already processing $contact")
            return
        }

        try {
            // Usar o SBN mais recente para reply action
            val lastSbn = contactMessages.last().sbn
            val replyAction = lastSbn.notification.actions?.find {
                it.remoteInputs?.isNotEmpty() == true
            }
            if (replyAction == null) {
                Log.d(TAG, "SKIP: no reply action for $contact")
                return
            }

            // Concatenar mensagens do contato (agrupamento)
            val fullMessage = contactMessages.joinToString("\n") { it.text }

            Log.i(TAG, "=== PROCESS: $contact msgs=${contactMessages.size} text='${fullMessage.take(80)}'")

            processMessage(contact, fullMessage, replyAction, lastSbn)
        } catch (e: Exception) {
            Log.e(TAG, "Error processing buffered messages", e)
            NotificationBridge.sendError(this@WhatsAppNotificationListener, e.message ?: "Unknown error")
        } finally {
            delay(POST_REPLY_BLOCK_MS)
            isProcessing.remove(contact)
            Log.d(TAG, "=== UNLOCKED: $contact")
        }
    }

    // ══════════════════════════════════════════════════════════
    //  CHAMAR API e enviar resposta
    // ══════════════════════════════════════════════════════════
    private suspend fun processMessage(
        sender: String,
        message: String,
        replyAction: Notification.Action,
        sbn: StatusBarNotification
    ) {
        val token = getAuthToken() ?: run {
            Log.w(TAG, "No auth token set")
            return
        }
        val apiUrl = getApiUrl()
        val phone = extractPhone(sbn).ifEmpty { sender }

        Log.d(TAG, "API_CALL: phone=$phone sender=$sender msg='${message.take(50)}'")

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

        Log.d(TAG, "API_RESPONSE: reply='${reply.take(60)}' error='$error' blocked=$blocked")

        NotificationBridge.sendMessageProcessed(this, sender, phone, message, reply)

        if (error.isNotEmpty()) {
            Log.w(TAG, "API error flag: $error — NOT replying")
            NotificationBridge.sendError(this, error)
            return
        }
        if (blocked) {
            Log.d(TAG, "Blocked by rules — NOT replying")
            return
        }
        if (reply.isEmpty()) {
            Log.d(TAG, "Empty reply — NOT replying")
            return
        }

        // Gravar resposta no cache ANTES de enviar (para detectar from_me depois)
        val cacheKey = "${sender}:${reply.trim().take(80).lowercase()}"
        sentRepliesCache[cacheKey] = System.currentTimeMillis()

        // Enviar resposta via WhatsApp
        val intent = Intent()
        val bundle = Bundle()
        replyAction.remoteInputs?.forEach { remoteInput ->
            bundle.putCharSequence(remoteInput.resultKey, reply)
        }
        RemoteInput.addResultsToIntent(replyAction.remoteInputs, intent, bundle)
        replyAction.actionIntent.send(this, 0, intent)

        cancelNotification(sbn.key)
        Log.i(TAG, "REPLIED: $sender → '${reply.take(60)}'")

        // Cleanup periódico
        scope.launch {
            delay(120_000)
            cleanupOldEntries()
        }
    }

    // ══════════════════════════════════════════════════════════
    //  LOGGING REMOTO: envia dados de cada notificação para o backend
    // ══════════════════════════════════════════════════════════
    private fun sendRemoteLog(
        contact: String, text: String, time: Long, fromMe: Boolean,
        sender: String?, key: String, pkg: String, flags: Int,
        extraText: String, allMessages: String
    ) {
        scope.launch {
            try {
                val token = getAuthToken() ?: return@launch
                val apiUrl = getApiUrl()

                val url = URL("$apiUrl/messages/notification-log")
                val conn = url.openConnection() as HttpURLConnection
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
                    put("extra_text", extraText.take(200))
                    put("time", time)
                    put("from_me", fromMe)
                    put("sender_field", sender ?: "null")
                    put("notification_key", key)
                    put("package", pkg)
                    put("flags", flags)
                    put("all_messages", allMessages)
                    put("is_processing", isProcessing[contact] == true)
                    put("buffer_size", messageBuffer[contact]?.size ?: 0)
                    put("app_version", "1.5")
                    put("ts", System.currentTimeMillis())
                }

                OutputStreamWriter(conn.outputStream).use { it.write(body.toString()) }
                conn.responseCode // force read
                conn.disconnect()
            } catch (e: Exception) {
                Log.w(TAG, "Remote log failed: ${e.message}")
            }
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
        sentRepliesCache.entries.removeAll { it.value < cutoff }
    }

    override fun onDestroy() {
        scope.cancel()
        isRunning = false
        super.onDestroy()
    }
}
