package cn.moncn.sing_box_windows.update

import android.content.Context
import android.content.Intent
import android.net.Uri
import android.os.Build
import android.util.Log
import androidx.core.content.FileProvider
import java.io.File

/**
 * APK 安装器
 * 负责触发系统安装界面
 */
class AppUpdateInstaller(private val context: Context) {

    /**
     * 检查是否有安装权限
     * Android 8.0+ 需要请求 REQUEST_INSTALL_PACKAGES 权限
     */
    fun canInstallPackage(): Boolean {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val canInstall = context.packageManager.canRequestPackageInstalls()
            Log.d("AppUpdateInstaller", "Can install package: $canInstall")
            return canInstall
        }
        return true
    }

    /**
     * 触发 APK 安装
     * @param apkFile 要安装的 APK 文件
     * @return 是否成功启动安装界面
     */
    fun installApk(apkFile: File): Boolean {
        return try {
            Log.d("AppUpdateInstaller", "installApk called, file: ${apkFile.absolutePath}, exists: ${apkFile.exists()}, size: ${apkFile.length()}")

            if (!apkFile.exists()) {
                throw IllegalStateException("APK 文件不存在: ${apkFile.absolutePath}")
            }

            val authority = "${context.packageName}.fileprovider"
            Log.d("AppUpdateInstaller", "FileProvider authority: $authority")
            val uri: Uri = FileProvider.getUriForFile(context, authority, apkFile)
            Log.d("AppUpdateInstaller", "FileProvider URI: $uri")

            // 使用 ACTION_VIEW，先设置 data，再设置 type
            val intent = Intent(Intent.ACTION_VIEW).apply {
                setDataAndType(uri, "application/vnd.android.package-archive")
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_GRANT_READ_URI_PERMISSION
            }

            Log.d("AppUpdateInstaller", "Starting install intent, action: ${intent.action}, type: ${intent.type}")
            context.startActivity(intent)
            Log.d("AppUpdateInstaller", "Install activity started successfully")
            true
        } catch (e: Exception) {
            Log.e("AppUpdateInstaller", "Install failed: ${e.message}", e)
            e.printStackTrace()
            false
        }
    }

    /**
     * 打开系统设置中的安装未知应用权限页面
     * Android 8.0+ 使用
     */
    fun openInstallPermissionSettings() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            val intent = Intent(
                android.provider.Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES
            ).apply {
                data = Uri.parse("package:${context.packageName}")
                addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
            }
            context.startActivity(intent)
        }
    }
}
