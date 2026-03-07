package com.aireply

import android.content.Context
import android.database.Cursor
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.net.Uri
import android.os.Build
import android.provider.MediaStore
import android.service.notification.StatusBarNotification
import android.util.Base64
import android.util.Log
import java.io.ByteArrayOutputStream

/**
 * Extrai mídia (imagem/áudio) de notificações do WhatsApp e do MediaStore.
 */
object MediaExtractor {

    private const val TAG = "MediaExtractor"
    private const val MAX_IMAGE_SIZE = 800
    private const val MAX_AUDIO_SIZE_BYTES = 5 * 1024 * 1024 // 5MB

    data class MediaResult(
        val type: String,       // "text", "image", "audio", "video", "sticker"
        val textContent: String, // Texto da notificação ou "[Imagem]", "[Áudio]", etc.
        val base64Data: String?, // Base64 da mídia (null se text)
        val mimeType: String?   // "image/jpeg", "audio/ogg", etc.
    )

    /**
     * Detecta o tipo de mensagem a partir do texto da notificação.
     */
    fun detectMessageType(text: String): String {
        val lower = text.lowercase().trim()
        return when {
            lower.contains("mensagem de voz") || lower.contains("voice message") ||
            lower == "🎤" || lower.startsWith("🎤") -> "audio"

            lower.contains("figurinha") || lower.contains("sticker") -> "sticker"

            lower == "📷 foto" || lower == "📷 photo" || lower == "foto" ||
            lower.startsWith("📷") || lower.contains("imagem") -> "image"

            lower == "📹 vídeo" || lower == "📹 video" || lower.startsWith("📹") -> "video"

            lower.contains("documento") || lower.contains("document") ||
            lower.startsWith("📄") -> "document"

            lower.contains("contato") || lower.contains("contact") ||
            lower.startsWith("👤") -> "contact"

            lower.contains("localização") || lower.contains("location") ||
            lower.startsWith("📍") -> "location"

            else -> "text"
        }
    }

    /**
     * Extrai imagem do BigPictureStyle da notificação.
     */
    fun extractImageFromNotification(sbn: StatusBarNotification): String? {
        try {
            val extras = sbn.notification.extras ?: return null

            // BigPictureStyle → EXTRA_PICTURE
            @Suppress("DEPRECATION")
            val bitmap = extras.getParcelable<Bitmap>(android.app.Notification.EXTRA_PICTURE)
            if (bitmap != null) {
                return bitmapToBase64(bitmap)
            }

            // Fallback: EXTRA_LARGE_ICON
            @Suppress("DEPRECATION")
            val icon = extras.getParcelable<Bitmap>(android.app.Notification.EXTRA_LARGE_ICON)
            if (icon != null && icon.width > 100 && icon.height > 100) {
                return bitmapToBase64(icon)
            }
        } catch (e: Exception) {
            Log.e(TAG, "Erro ao extrair imagem da notificação", e)
        }
        return null
    }

    /**
     * Busca o áudio mais recente do WhatsApp via MediaStore.
     * Retorna base64 do arquivo de áudio ou null.
     */
    fun findRecentWhatsAppAudio(context: Context, maxAgeMs: Long = 30_000): String? {
        try {
            val uri: Uri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                MediaStore.Audio.Media.getContentUri(MediaStore.VOLUME_EXTERNAL)
            } else {
                MediaStore.Audio.Media.EXTERNAL_CONTENT_URI
            }

            val projection = arrayOf(
                MediaStore.Audio.Media._ID,
                MediaStore.Audio.Media.DATE_ADDED,
                MediaStore.Audio.Media.SIZE,
                MediaStore.Audio.Media.MIME_TYPE,
            )

            val cutoffSeconds = (System.currentTimeMillis() - maxAgeMs) / 1000
            val selection = "${MediaStore.Audio.Media.DATE_ADDED} > ? AND " +
                "(${MediaStore.Audio.Media.DATA} LIKE ? OR ${MediaStore.Audio.Media.DATA} LIKE ?)"
            val selectionArgs = arrayOf(
                cutoffSeconds.toString(),
                "%WhatsApp%Voice Notes%",
                "%WhatsApp%Audio%"
            )
            val sortOrder = "${MediaStore.Audio.Media.DATE_ADDED} DESC"

            val cursor: Cursor? = context.contentResolver.query(
                uri, projection, selection, selectionArgs, sortOrder
            )

            cursor?.use {
                if (it.moveToFirst()) {
                    val id = it.getLong(it.getColumnIndexOrThrow(MediaStore.Audio.Media._ID))
                    val size = it.getLong(it.getColumnIndexOrThrow(MediaStore.Audio.Media.SIZE))
                    val mimeType = it.getString(it.getColumnIndexOrThrow(MediaStore.Audio.Media.MIME_TYPE))

                    if (size > MAX_AUDIO_SIZE_BYTES) {
                        Log.w(TAG, "Áudio muito grande: $size bytes")
                        return null
                    }

                    val audioUri = Uri.withAppendedPath(uri, id.toString())
                    val inputStream = context.contentResolver.openInputStream(audioUri)
                    inputStream?.use { stream ->
                        val bytes = stream.readBytes()
                        Log.i(TAG, "Áudio encontrado: ${bytes.size} bytes, mime=$mimeType")
                        return Base64.encodeToString(bytes, Base64.NO_WRAP)
                    }
                }
            }

            Log.d(TAG, "Nenhum áudio recente do WhatsApp encontrado")
        } catch (e: Exception) {
            Log.e(TAG, "Erro ao buscar áudio do WhatsApp", e)
        }
        return null
    }

    /**
     * Busca a imagem mais recente do WhatsApp via MediaStore.
     * Usado como fallback quando BigPictureStyle não contém a imagem.
     */
    fun findRecentWhatsAppImage(context: Context, maxAgeMs: Long = 15_000): String? {
        try {
            val uri: Uri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                MediaStore.Images.Media.getContentUri(MediaStore.VOLUME_EXTERNAL)
            } else {
                MediaStore.Images.Media.EXTERNAL_CONTENT_URI
            }

            val projection = arrayOf(
                MediaStore.Images.Media._ID,
                MediaStore.Images.Media.DATE_ADDED,
                MediaStore.Images.Media.SIZE,
            )

            val cutoffSeconds = (System.currentTimeMillis() - maxAgeMs) / 1000
            val selection = "${MediaStore.Images.Media.DATE_ADDED} > ? AND " +
                "${MediaStore.Images.Media.DATA} LIKE ?"
            val selectionArgs = arrayOf(
                cutoffSeconds.toString(),
                "%WhatsApp%Images%"
            )
            val sortOrder = "${MediaStore.Images.Media.DATE_ADDED} DESC"

            val cursor: Cursor? = context.contentResolver.query(
                uri, projection, selection, selectionArgs, sortOrder
            )

            cursor?.use {
                if (it.moveToFirst()) {
                    val id = it.getLong(it.getColumnIndexOrThrow(MediaStore.Images.Media._ID))
                    val imageUri = Uri.withAppendedPath(uri, id.toString())

                    val inputStream = context.contentResolver.openInputStream(imageUri)
                    inputStream?.use { stream ->
                        val bitmap = BitmapFactory.decodeStream(stream)
                        if (bitmap != null) {
                            Log.i(TAG, "Imagem encontrada via MediaStore: ${bitmap.width}x${bitmap.height}")
                            return bitmapToBase64(bitmap)
                        }
                    }
                }
            }

            Log.d(TAG, "Nenhuma imagem recente do WhatsApp encontrada")
        } catch (e: Exception) {
            Log.e(TAG, "Erro ao buscar imagem do WhatsApp", e)
        }
        return null
    }

    /**
     * Converte Bitmap para base64 JPEG, redimensionando se necessário.
     */
    private fun bitmapToBase64(bitmap: Bitmap): String {
        val scaled = if (bitmap.width > MAX_IMAGE_SIZE || bitmap.height > MAX_IMAGE_SIZE) {
            val ratio = minOf(
                MAX_IMAGE_SIZE.toFloat() / bitmap.width,
                MAX_IMAGE_SIZE.toFloat() / bitmap.height
            )
            Bitmap.createScaledBitmap(
                bitmap,
                (bitmap.width * ratio).toInt(),
                (bitmap.height * ratio).toInt(),
                true
            )
        } else {
            bitmap
        }

        val stream = ByteArrayOutputStream()
        scaled.compress(Bitmap.CompressFormat.JPEG, 80, stream)
        val bytes = stream.toByteArray()
        Log.i(TAG, "Imagem convertida: ${bytes.size} bytes (${scaled.width}x${scaled.height})")
        return Base64.encodeToString(bytes, Base64.NO_WRAP)
    }
}
