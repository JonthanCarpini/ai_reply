package com.aireply

import android.content.ComponentName
import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.provider.Settings
import com.facebook.react.bridge.*
import com.facebook.react.modules.core.DeviceEventManagerModule

class NotificationBridge(reactContext: ReactApplicationContext) :
    ReactContextBaseJavaModule(reactContext) {

    companion object {
        private var reactContextRef: ReactApplicationContext? = null

        fun sendServiceStatus(context: Context, running: Boolean) {
            val ctx = reactContextRef ?: return
            val params = Arguments.createMap().apply {
                putBoolean("running", running)
            }
            sendEvent(ctx, "onServiceStatusChange", params)
        }

        fun sendMessageProcessed(
            context: Context,
            contactName: String,
            contactPhone: String,
            message: String,
            reply: String,
            correlationId: String,
            batchId: String,
            batchSize: Int
        ) {
            val ctx = reactContextRef ?: return
            val params = Arguments.createMap().apply {
                putString("contactName", contactName)
                putString("contactPhone", contactPhone)
                putString("message", message)
                putString("reply", reply)
                putString("correlationId", correlationId)
                putString("batchId", batchId)
                putInt("batchSize", batchSize)
                putString("timestamp", System.currentTimeMillis().toString())
            }
            sendEvent(ctx, "onMessageProcessed", params)
        }

        fun sendError(context: Context, error: String) {
            val ctx = reactContextRef ?: return
            val params = Arguments.createMap().apply {
                putString("error", error)
            }
            sendEvent(ctx, "onError", params)
        }

        private fun sendEvent(ctx: ReactApplicationContext, name: String, params: WritableMap) {
            try {
                ctx.getJSModule(DeviceEventManagerModule.RCTDeviceEventEmitter::class.java)
                    .emit(name, params)
            } catch (_: Exception) {}
        }
    }

    override fun initialize() {
        super.initialize()
        reactContextRef = reactApplicationContext
    }

    override fun getName(): String = "NotificationBridge"

    @ReactMethod
    fun hasNotificationAccess(promise: Promise) {
        val ctx = reactApplicationContext
        val cn = ComponentName(ctx, WhatsAppNotificationListener::class.java)
        val flat = Settings.Secure.getString(ctx.contentResolver, "enabled_notification_listeners") ?: ""
        promise.resolve(flat.contains(cn.flattenToString()))
    }

    @ReactMethod
    fun openNotificationAccessSettings() {
        val intent = Intent(Settings.ACTION_NOTIFICATION_LISTENER_SETTINGS).apply {
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        reactApplicationContext.startActivity(intent)
    }

    @ReactMethod
    fun openAppSettings() {
        val ctx = reactApplicationContext
        val intent = Intent(Settings.ACTION_APPLICATION_DETAILS_SETTINGS).apply {
            data = Uri.parse("package:${ctx.packageName}")
            addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
        }
        ctx.startActivity(intent)
    }

    @ReactMethod
    fun needsRestrictedSettingsPermission(promise: Promise) {
        promise.resolve(Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU)
    }

    @ReactMethod
    fun startListenerService(promise: Promise) {
        try {
            val ctx = reactApplicationContext
            val intent = Intent(ctx, KeepAliveService::class.java)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                ctx.startForegroundService(intent)
            } else {
                ctx.startService(intent)
            }
            promise.resolve(true)
        } catch (e: Exception) {
            promise.reject("START_ERROR", e.message)
        }
    }

    @ReactMethod
    fun stopListenerService(promise: Promise) {
        try {
            val ctx = reactApplicationContext
            ctx.stopService(Intent(ctx, KeepAliveService::class.java))
            promise.resolve(true)
        } catch (e: Exception) {
            promise.reject("STOP_ERROR", e.message)
        }
    }

    @ReactMethod
    fun isServiceRunning(promise: Promise) {
        try {
            val ctx = reactApplicationContext
            val enabled = android.provider.Settings.Secure.getString(
                ctx.contentResolver,
                "enabled_notification_listeners"
            )
            val packageName = ctx.packageName
            val isEnabled = enabled?.contains(packageName) == true
            
            // Se o listener está habilitado mas isRunning é false, tentar reconectar
            if (isEnabled && !WhatsAppNotificationListener.isRunning) {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                    android.service.notification.NotificationListenerService.requestRebind(
                        android.content.ComponentName(ctx, WhatsAppNotificationListener::class.java)
                    )
                }
            }
            
            promise.resolve(isEnabled)
        } catch (e: Exception) {
            promise.resolve(WhatsAppNotificationListener.isRunning)
        }
    }

    @ReactMethod
    fun setAuthToken(token: String) {
        reactApplicationContext.getSharedPreferences("ai_reply_prefs", Context.MODE_PRIVATE)
            .edit()
            .putString("auth_token", token)
            .apply()
    }

    @ReactMethod
    fun setApiUrl(url: String) {
        reactApplicationContext.getSharedPreferences("ai_reply_prefs", Context.MODE_PRIVATE)
            .edit()
            .putString("api_url", url)
            .apply()
    }

    @ReactMethod
    fun setWhatsAppPackages(packages: ReadableArray) {
        val set = mutableSetOf<String>()
        for (i in 0 until packages.size()) {
            packages.getString(i)?.let { set.add(it) }
        }
        reactApplicationContext.getSharedPreferences("ai_reply_prefs", Context.MODE_PRIVATE)
            .edit()
            .putStringSet("whatsapp_packages", set)
            .apply()
    }

    @ReactMethod
    fun getWhatsAppPackages(promise: Promise) {
        val prefs = reactApplicationContext.getSharedPreferences("ai_reply_prefs", Context.MODE_PRIVATE)
        val set = prefs.getStringSet("whatsapp_packages", setOf("com.whatsapp", "com.whatsapp.w4b"))
        val arr = Arguments.createArray()
        set?.forEach { arr.pushString(it) }
        promise.resolve(arr)
    }

    @ReactMethod
    fun requestIgnoreBatteryOptimization() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            val intent = Intent(Settings.ACTION_IGNORE_BATTERY_OPTIMIZATION_SETTINGS).apply {
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            reactApplicationContext.startActivity(intent)
        }
    }

    @ReactMethod
    fun addListener(eventName: String) {}

    @ReactMethod
    fun removeListeners(count: Int) {}
}
