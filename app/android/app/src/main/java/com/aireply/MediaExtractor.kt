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
     * Busca o áudio mais recente do WhatsApp.
     * Tenta 3 abordagens em ordem:
     * 1. Acesso direto ao filesystem (MANAGE_EXTERNAL_STORAGE)
     * 2. MediaStore.Files (inclui .opus que não aparece em Audio)
     * 3. MediaStore.Audio (fallback)
     */
    fun findRecentWhatsAppAudio(context: Context, maxAgeMs: Long = 60_000): String? {
        // Método 1: Acesso direto ao filesystem
        val directResult = findAudioViaFilesystem(maxAgeMs)
        if (directResult != null) return directResult

        // Método 2: MediaStore.Files
        val filesResult = findAudioViaMediaStoreFiles(context, maxAgeMs)
        if (filesResult != null) return filesResult

        // Método 3: MediaStore.Audio
        val audioResult = findAudioViaMediaStoreAudio(context, maxAgeMs)
        if (audioResult != null) return audioResult

        Log.d(TAG, "Nenhum áudio recente do WhatsApp encontrado (3 métodos tentados)")
        return null
    }

    /**
     * Método 1: Acesso direto ao filesystem do WhatsApp.
     * Funciona com MANAGE_EXTERNAL_STORAGE no Android 11+.
     */
    private fun findAudioViaFilesystem(maxAgeMs: Long): String? {
        try {
            val basePaths = listOf(
                // Android 11+ (Scoped Storage)
                "/storage/emulated/0/Android/media/com.whatsapp/WhatsApp/Media/WhatsApp Voice Notes",
                "/storage/emulated/0/Android/media/com.whatsapp.w4b/WhatsApp Business/Media/WhatsApp Voice Notes",
                // Android 10 e anterior
                "/storage/emulated/0/WhatsApp/Media/WhatsApp Voice Notes",
                // Áudios recebidos (não voice notes)
                "/storage/emulated/0/Android/media/com.whatsapp/WhatsApp/Media/WhatsApp Audio",
                "/storage/emulated/0/WhatsApp/Media/WhatsApp Audio",
            )

            val cutoff = System.currentTimeMillis() - maxAgeMs
            var newestFile: java.io.File? = null
            var newestTime = 0L

            for (basePath in basePaths) {
                val dir = java.io.File(basePath)
                if (!dir.exists() || !dir.canRead()) continue

                // Buscar recursivamente (voice notes ficam em subpastas por data)
                val files = dir.walkTopDown()
                    .maxDepth(3)
                    .filter { it.isFile && it.lastModified() > cutoff }
                    .filter { it.extension in listOf("opus", "ogg", "m4a", "mp3", "aac", "wav", "3gp") }
                    .toList()

                for (file in files) {
                    if (file.lastModified() > newestTime) {
                        newestTime = file.lastModified()
                        newestFile = file
                    }
                }
            }

            if (newestFile != null && newestFile.length() <= MAX_AUDIO_SIZE_BYTES) {
                val bytes = newestFile.readBytes()
                Log.i(TAG, "Áudio via filesystem: ${newestFile.absolutePath} (${bytes.size} bytes)")
                return Base64.encodeToString(bytes, Base64.NO_WRAP)
            } else if (newestFile != null) {
                Log.w(TAG, "Áudio muito grande: ${newestFile.length()} bytes")
            }
        } catch (e: Exception) {
            Log.d(TAG, "Filesystem access failed: ${e.message}")
        }
        return null
    }

    /**
     * Método 2: MediaStore.Files (pega .opus que não aparece em MediaStore.Audio).
     */
    private fun findAudioViaMediaStoreFiles(context: Context, maxAgeMs: Long): String? {
        try {
            val uri = MediaStore.Files.getContentUri("external")
            val projection = arrayOf(
                MediaStore.Files.FileColumns._ID,
                MediaStore.Files.FileColumns.DATE_MODIFIED,
                MediaStore.Files.FileColumns.SIZE,
                MediaStore.Files.FileColumns.MIME_TYPE,
                MediaStore.Files.FileColumns.DATA,
            )

            val cutoffSeconds = (System.currentTimeMillis() - maxAgeMs) / 1000
            val selection = "${MediaStore.Files.FileColumns.DATE_MODIFIED} > ? AND " +
                "(${MediaStore.Files.FileColumns.DATA} LIKE ? OR ${MediaStore.Files.FileColumns.DATA} LIKE ? OR ${MediaStore.Files.FileColumns.DATA} LIKE ?)"
            val selectionArgs = arrayOf(
                cutoffSeconds.toString(),
                "%WhatsApp%Voice Notes%.opus",
                "%WhatsApp%Voice Notes%.ogg",
                "%WhatsApp%Audio%"
            )
            val sortOrder = "${MediaStore.Files.FileColumns.DATE_MODIFIED} DESC"

            val cursor = context.contentResolver.query(uri, projection, selection, selectionArgs, sortOrder)
            cursor?.use {
                if (it.moveToFirst()) {
                    val id = it.getLong(it.getColumnIndexOrThrow(MediaStore.Files.FileColumns._ID))
                    val size = it.getLong(it.getColumnIndexOrThrow(MediaStore.Files.FileColumns.SIZE))
                    val path = it.getString(it.getColumnIndexOrThrow(MediaStore.Files.FileColumns.DATA)) ?: ""

                    if (size > MAX_AUDIO_SIZE_BYTES) {
                        Log.w(TAG, "Áudio muito grande via Files: $size bytes")
                        return null
                    }

                    val audioUri = Uri.withAppendedPath(uri, id.toString())
                    context.contentResolver.openInputStream(audioUri)?.use { stream ->
                        val bytes = stream.readBytes()
                        Log.i(TAG, "Áudio via MediaStore.Files: $path (${bytes.size} bytes)")
                        return Base64.encodeToString(bytes, Base64.NO_WRAP)
                    }
                }
            }
        } catch (e: Exception) {
            Log.d(TAG, "MediaStore.Files failed: ${e.message}")
        }
        return null
    }

    /**
     * Método 3: MediaStore.Audio (fallback clássico).
     */
    private fun findAudioViaMediaStoreAudio(context: Context, maxAgeMs: Long): String? {
        try {
            val uri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                MediaStore.Audio.Media.getContentUri(MediaStore.VOLUME_EXTERNAL)
            } else {
                MediaStore.Audio.Media.EXTERNAL_CONTENT_URI
            }

            val projection = arrayOf(
                MediaStore.Audio.Media._ID,
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

            val cursor = context.contentResolver.query(uri, projection, selection, selectionArgs, sortOrder)
            cursor?.use {
                if (it.moveToFirst()) {
                    val id = it.getLong(it.getColumnIndexOrThrow(MediaStore.Audio.Media._ID))
                    val size = it.getLong(it.getColumnIndexOrThrow(MediaStore.Audio.Media.SIZE))

                    if (size > MAX_AUDIO_SIZE_BYTES) return null

                    val audioUri = Uri.withAppendedPath(uri, id.toString())
                    context.contentResolver.openInputStream(audioUri)?.use { stream ->
                        val bytes = stream.readBytes()
                        Log.i(TAG, "Áudio via MediaStore.Audio: ${bytes.size} bytes")
                        return Base64.encodeToString(bytes, Base64.NO_WRAP)
                    }
                }
            }
        } catch (e: Exception) {
            Log.d(TAG, "MediaStore.Audio failed: ${e.message}")
        }
        return null
    }

    /**
     * Busca a imagem mais recente do WhatsApp.
     * 1. Filesystem direto (MANAGE_EXTERNAL_STORAGE)
     * 2. MediaStore (fallback)
     */
    fun findRecentWhatsAppImage(context: Context, maxAgeMs: Long = 30_000): String? {
        // Método 1: Filesystem direto
        val fsResult = findImageViaFilesystem(maxAgeMs)
        if (fsResult != null) return fsResult

        // Método 2: MediaStore
        try {
            val uri: Uri = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                MediaStore.Images.Media.getContentUri(MediaStore.VOLUME_EXTERNAL)
            } else {
                MediaStore.Images.Media.EXTERNAL_CONTENT_URI
            }

            val projection = arrayOf(
                MediaStore.Images.Media._ID,
                MediaStore.Images.Media.DATE_ADDED,
            )

            val cutoffSeconds = (System.currentTimeMillis() - maxAgeMs) / 1000
            val selection = "${MediaStore.Images.Media.DATE_ADDED} > ? AND " +
                "${MediaStore.Images.Media.DATA} LIKE ?"
            val selectionArgs = arrayOf(
                cutoffSeconds.toString(),
                "%WhatsApp%Images%"
            )
            val sortOrder = "${MediaStore.Images.Media.DATE_ADDED} DESC"

            val cursor = context.contentResolver.query(uri, projection, selection, selectionArgs, sortOrder)
            cursor?.use {
                if (it.moveToFirst()) {
                    val id = it.getLong(it.getColumnIndexOrThrow(MediaStore.Images.Media._ID))
                    val imageUri = Uri.withAppendedPath(uri, id.toString())

                    context.contentResolver.openInputStream(imageUri)?.use { stream ->
                        val bitmap = BitmapFactory.decodeStream(stream)
                        if (bitmap != null) {
                            Log.i(TAG, "Imagem via MediaStore: ${bitmap.width}x${bitmap.height}")
                            return bitmapToBase64(bitmap)
                        }
                    }
                }
            }
        } catch (e: Exception) {
            Log.d(TAG, "MediaStore image failed: ${e.message}")
        }

        Log.d(TAG, "Nenhuma imagem recente do WhatsApp encontrada")
        return null
    }

    /**
     * Busca imagem recente do WhatsApp via acesso direto ao filesystem.
     */
    private fun findImageViaFilesystem(maxAgeMs: Long): String? {
        try {
            val basePaths = listOf(
                "/storage/emulated/0/Android/media/com.whatsapp/WhatsApp/Media/WhatsApp Images",
                "/storage/emulated/0/Android/media/com.whatsapp.w4b/WhatsApp Business/Media/WhatsApp Images",
                "/storage/emulated/0/WhatsApp/Media/WhatsApp Images",
            )

            val cutoff = System.currentTimeMillis() - maxAgeMs
            var newestFile: java.io.File? = null
            var newestTime = 0L

            for (basePath in basePaths) {
                val dir = java.io.File(basePath)
                if (!dir.exists() || !dir.canRead()) continue

                val files = dir.walkTopDown()
                    .maxDepth(2)
                    .filter { it.isFile && it.lastModified() > cutoff }
                    .filter { it.extension.lowercase() in listOf("jpg", "jpeg", "png", "webp") }
                    .toList()

                for (file in files) {
                    if (file.lastModified() > newestTime) {
                        newestTime = file.lastModified()
                        newestFile = file
                    }
                }
            }

            if (newestFile != null) {
                val bitmap = BitmapFactory.decodeFile(newestFile.absolutePath)
                if (bitmap != null) {
                    Log.i(TAG, "Imagem via filesystem: ${newestFile.absolutePath} (${bitmap.width}x${bitmap.height})")
                    return bitmapToBase64(bitmap)
                }
            }
        } catch (e: Exception) {
            Log.d(TAG, "Filesystem image failed: ${e.message}")
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
