package com.aireply

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.net.Uri
import android.os.Build
import android.os.Bundle
import android.os.Environment
import android.provider.Settings
import android.util.Log
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import com.facebook.react.ReactActivity
import com.facebook.react.ReactActivityDelegate
import com.facebook.react.defaults.DefaultNewArchitectureEntryPoint.fabricEnabled
import com.facebook.react.defaults.DefaultReactActivityDelegate

class MainActivity : ReactActivity() {

  companion object {
      private const val TAG = "AIReplyMain"
      private const val PERMISSION_REQUEST_CODE = 1001
  }

  override fun getMainComponentName(): String = "AIReplyApp"

  override fun createReactActivityDelegate(): ReactActivityDelegate =
      DefaultReactActivityDelegate(this, mainComponentName, fabricEnabled)

  override fun onCreate(savedInstanceState: Bundle?) {
      super.onCreate(savedInstanceState)
      requestMediaPermissions()
  }

  private fun requestMediaPermissions() {
      if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.R) {
          // Android 11+: solicitar MANAGE_EXTERNAL_STORAGE para acessar WhatsApp media
          if (!Environment.isExternalStorageManager()) {
              Log.i(TAG, "Solicitando MANAGE_EXTERNAL_STORAGE")
              try {
                  val intent = Intent(Settings.ACTION_MANAGE_APP_ALL_FILES_ACCESS_PERMISSION)
                  intent.data = Uri.parse("package:$packageName")
                  startActivity(intent)
              } catch (e: Exception) {
                  val intent = Intent(Settings.ACTION_MANAGE_ALL_FILES_ACCESS_PERMISSION)
                  startActivity(intent)
              }
          } else {
              Log.i(TAG, "MANAGE_EXTERNAL_STORAGE já concedida")
          }

          // Também pedir READ_MEDIA_AUDIO e READ_MEDIA_IMAGES (Android 13+)
          if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
              val perms = mutableListOf<String>()
              if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_MEDIA_AUDIO)
                  != PackageManager.PERMISSION_GRANTED) {
                  perms.add(Manifest.permission.READ_MEDIA_AUDIO)
              }
              if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_MEDIA_IMAGES)
                  != PackageManager.PERMISSION_GRANTED) {
                  perms.add(Manifest.permission.READ_MEDIA_IMAGES)
              }
              if (perms.isNotEmpty()) {
                  ActivityCompat.requestPermissions(this, perms.toTypedArray(), PERMISSION_REQUEST_CODE)
              }
          }
      } else {
          // Android 10 e anterior: READ_EXTERNAL_STORAGE
          if (ContextCompat.checkSelfPermission(this, Manifest.permission.READ_EXTERNAL_STORAGE)
              != PackageManager.PERMISSION_GRANTED) {
              ActivityCompat.requestPermissions(
                  this,
                  arrayOf(Manifest.permission.READ_EXTERNAL_STORAGE),
                  PERMISSION_REQUEST_CODE
              )
          }
      }
  }

  override fun onRequestPermissionsResult(requestCode: Int, permissions: Array<String>, grantResults: IntArray) {
      super.onRequestPermissionsResult(requestCode, permissions, grantResults)
      if (requestCode == PERMISSION_REQUEST_CODE) {
          for (i in permissions.indices) {
              val granted = grantResults.getOrNull(i) == PackageManager.PERMISSION_GRANTED
              Log.i(TAG, "Permission ${permissions[i]}: ${if (granted) "GRANTED" else "DENIED"}")
          }
      }
  }
}
