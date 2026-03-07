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
        var isRunning = false
            private set
    }

    private val scope = CoroutineScope(Dispatchers.IO + SupervisorJob())

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
        Log.d(TAG, "Notification from: ${sbn.packageName}")

        val allowedPackages = getAllowedPackages()
        if (sbn.packageName !in allowedPackages) {
            Log.d(TAG, "SKIP: package ${sbn.packageName} not in allowed: $allowedPackages")
            return
        }

        val extras = sbn.notification.extras ?: run {
            Log.d(TAG, "SKIP: no extras")
            return
        }
        val title = extras.getString(Notification.EXTRA_TITLE) ?: run {
            Log.d(TAG, "SKIP: no title")
            return
        }
        val text = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString() ?: run {
            Log.d(TAG, "SKIP: no text for title=$title")
            return
        }

        Log.d(TAG, "Notification: title=$title text=$text")

        // Filtrar mensagens de grupo (formato "Fulano: mensagem" no text COM título de grupo)
        val isGroupMessage = extras.getBoolean(Notification.EXTRA_IS_GROUP_CONVERSATION, false)
        if (isGroupMessage) {
            Log.d(TAG, "SKIP: group message from $title")
            return
        }

        if (text.isBlank() || text.length < 2) {
            Log.d(TAG, "SKIP: text too short")
            return
        }

        val replyAction = sbn.notification.actions?.find { action ->
            action.remoteInputs?.isNotEmpty() == true
        } ?: run {
            Log.d(TAG, "SKIP: no reply action for $title (actions=${sbn.notification.actions?.size ?: 0})")
            return
        }

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

        Log.d(TAG, "Request body: $body")
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

        NotificationBridge.sendMessageProcessed(this, sender, phone, message, reply)

        if (reply.isNotEmpty()) {
            val intent = Intent()
            val bundle = Bundle()
            replyAction.remoteInputs?.forEach { remoteInput ->
                bundle.putCharSequence(remoteInput.resultKey, reply)
            }
            RemoteInput.addResultsToIntent(replyAction.remoteInputs, intent, bundle)
            replyAction.actionIntent.send(this, 0, intent)

            cancelNotification(sbn.key)
            Log.i(TAG, "Replied to $sender: ${reply.take(50)}...")
        }
    }

    private fun extractPhone(sbn: StatusBarNotification): String {
        val phoneRegex = Regex("\\+?\\d{10,15}")

        // Try notification key first
        val key = sbn.key ?: ""
        phoneRegex.find(key)?.value?.let { return it }

        // Try tag
        sbn.tag?.let { tag ->
            phoneRegex.find(tag)?.value?.let { return it }
        }

        // Try extras for android.conversationTitle or other fields
        val extras = sbn.notification.extras
        val convTitle = extras?.getString("android.conversationTitle") ?: ""
        phoneRegex.find(convTitle)?.value?.let { return it }

        // Try title (contact name may have phone)
        val title = extras?.getString(Notification.EXTRA_TITLE) ?: ""
        phoneRegex.find(title)?.value?.let { return it }

        Log.d(TAG, "Could not extract phone from notification. key=$key tag=${sbn.tag}")
        return ""
    }

    override fun onDestroy() {
        scope.cancel()
        isRunning = false
        super.onDestroy()
    }
}
