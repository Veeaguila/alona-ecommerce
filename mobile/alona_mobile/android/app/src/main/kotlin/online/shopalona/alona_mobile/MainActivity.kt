package online.shopalona.alona_mobile

import android.accounts.AccountManager
import android.app.Activity
import android.content.Intent
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.MethodChannel

class MainActivity : FlutterActivity() {
    private val channelName = "online.shopalona.alona_mobile/google_picker"
    private val pickAccountRequestCode = 9001
    private var pendingResult: MethodChannel.Result? = null

    @Suppress("DEPRECATION")
    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)
        MethodChannel(flutterEngine.dartExecutor.binaryMessenger, channelName)
            .setMethodCallHandler { call, result ->
                if (call.method == "pickGoogleAccount") {
                    if (pendingResult != null) {
                        pendingResult?.success(null)
                    }
                    pendingResult = result
                    try {
                        val intent = AccountManager.newChooseAccountIntent(
                            null,
                            null,
                            arrayOf("com.google"),
                            null,
                            null,
                            null,
                            null
                        )
                        startActivityForResult(intent, pickAccountRequestCode)
                    } catch (e: Exception) {
                        pendingResult = null
                        result.error("PICKER_ERROR", e.localizedMessage, null)
                    }
                } else {
                    result.notImplemented()
                }
            }
    }

    @Suppress("DEPRECATION")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode == pickAccountRequestCode) {
            val callback = pendingResult
            pendingResult = null
            if (callback != null) {
                if (resultCode == Activity.RESULT_OK && data != null) {
                    val email = data.getStringExtra(AccountManager.KEY_ACCOUNT_NAME)
                    if (!email.isNullOrEmpty()) {
                        callback.success(mapOf("email" to email))
                    } else {
                        callback.success(null)
                    }
                } else {
                    callback.success(null)
                }
            }
        }
    }
}
