package cn.moncn.sing_box_windows.update

import android.content.Context
import android.net.ConnectivityManager
import android.net.NetworkCapabilities
import android.os.Build
import android.util.Log
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.flow.catch
import kotlinx.coroutines.flow.launchIn
import kotlinx.coroutines.flow.onCompletion
import kotlinx.coroutines.flow.onEach
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

/**
 * 应用更新管理器
 * 协调版本检查、下载和安装流程
 */
class UpdateManager(private val context: Context) {

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.Main)
    private val config = UpdateConfig()
    private val checker = GitHubReleaseChecker(context, config)
    private val downloader = ApkDownloader(context)
    private val installer = AppUpdateInstaller(context)

    /**
     * 获取当前应用版本号
     */
    fun getCurrentVersion(): String {
        return try {
            val packageInfo = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                context.packageManager.getPackageInfo(context.packageName, 0)
            } else {
                @Suppress("DEPRECATION")
                context.packageManager.getPackageInfo(context.packageName, 0)
            }
            packageInfo.versionName ?: "1.0"
        } catch (e: Exception) {
            "1.0"
        }
    }

    /**
     * 检查是否有安装权限
     */
    fun canInstallPackage(): Boolean = installer.canInstallPackage()

    /**
     * 打开安装权限设置页面
     */
    fun openInstallPermissionSettings() = installer.openInstallPermissionSettings()

    /**
     * 检查更新
     * @param isManual 是否为手动检查（手动检查会显示提示）
     */
    fun checkUpdate(isManual: Boolean = false) {
        if (UpdateStore.isBusy) {
            return
        }

        UpdateStore.update(UpdateState.Checking)

        scope.launch {
            val result = checker.checkLatest(getCurrentVersion())

            when {
                result.error != null -> {
                    UpdateStore.update(UpdateState.Failed(result.error))
                    if (isManual) {
                        // 手动检查失败，需要通知用户
                        UpdateStore.setCheckTime()
                    }
                }
                result.hasUpdate && result.release != null -> {
                    UpdateStore.setCheckTime()
                    UpdateStore.setKnownVersion(result.release.tagName)
                    UpdateStore.update(UpdateState.UpdateAvailable(result.release))
                }
                else -> {
                    UpdateStore.setCheckTime()
                    UpdateStore.update(UpdateState.UpToDate)
                }
            }
        }
    }

    /**
     * 下载更新
     * @param release 要下载的 Release
     */
    fun downloadUpdate(release: GitHubRelease) {
        Log.d("UpdateManager", "downloadUpdate called, release: ${release.tagName}, assets: ${release.assets.size}")

        // 获取匹配当前设备的 APK 资源
        val asset = release.assets.firstOrNull() ?: run {
            Log.e("UpdateManager", "No assets found in release")
            UpdateStore.update(UpdateState.Failed("未找到匹配的 APK 文件"))
            return
        }

        Log.d("UpdateManager", "Selected asset: ${asset.name}, url: ${asset.downloadUrl}, size: ${asset.size}")

        // 检查是否已下载
        val downloadedFile = downloader.getDownloadedApk(asset)
        if (downloadedFile != null) {
            Log.d("UpdateManager", "File already downloaded: ${downloadedFile.absolutePath}")
            // 下载完成，直接触发安装
            installer.installApk(downloadedFile)
            UpdateStore.reset()
            return
        }

        // 检查网络条件
        val isWifi = isWifiConnected()
        Log.d("UpdateManager", "WiFi only: ${UpdateStore.wifiOnlyDownload}, isWiFi: $isWifi")
        if (UpdateStore.wifiOnlyDownload && !isWifi) {
            Log.w("UpdateManager", "Not on WiFi, download cancelled")
            UpdateStore.update(
                UpdateState.Failed("当前设置为仅在 Wi-Fi 下下载更新")
            )
            return
        }

        Log.d("UpdateManager", "Starting download...")
        scope.launch {
            downloader.downloadApk(asset)
                .onEach { progress ->
                    Log.d("UpdateManager", "Download progress: ${progress.percentage}%, ${progress.bytesDownloaded}/${progress.totalBytes}")
                    UpdateStore.update(
                        UpdateState.Downloading(release, progress)
                    )
                }
                .onCompletion { cause ->
                    Log.d("UpdateManager", "Download completed, cause: $cause")
                    if (cause == null) {
                        val file = downloader.getDownloadedApk(asset)
                        if (file != null) {
                            Log.d("UpdateManager", "Downloaded file: ${file.absolutePath}, size: ${file.length()}")
                            // 下载完成，直接触发安装
                            val installSuccess = installer.installApk(file)
                            Log.d("UpdateManager", "Install launched: $installSuccess")
                            // 重置状态
                            UpdateStore.reset()
                        } else {
                            Log.e("UpdateManager", "Downloaded file not found")
                            UpdateStore.update(
                                UpdateState.Failed("下载文件验证失败")
                            )
                        }
                    } else {
                        Log.e("UpdateManager", "Download failed: ${cause.message}", cause)
                    }
                }
                .catch { e ->
                    Log.e("UpdateManager", "Download exception: ${e.message}", e)
                    UpdateStore.update(
                        UpdateState.Failed("下载失败: ${e.message}")
                    )
                }
                .launchIn(this@launch)
        }
    }

    /**
     * 安装更新
     * @param apkFile 要安装的 APK 文件
     * @return 是否成功启动安装
     */
    fun installUpdate(apkFile: java.io.File): Boolean {
        return installer.installApk(apkFile)
    }

    /**
     * 忽略此更新
     */
    fun ignoreUpdate() {
        UpdateStore.reset()
    }

    /**
     * 清除下载缓存
     */
    fun clearCache() {
        scope.launch {
            downloader.clearCache()
        }
    }

    /**
     * 自动检查更新
     * 在应用启动时调用，根据配置决定是否检查
     */
    fun autoCheckIfNeeded() {
        if (!config.autoCheckEnabled) return
        if (!UpdateStore.shouldCheckForUpdates(config.checkInterval)) return
        if (!isNetworkAvailable()) return

        // 后台静默检查
        checkUpdate(isManual = false)
    }

    /**
     * 检查网络是否可用
     */
    private fun isNetworkAvailable(): Boolean {
        val connectivityManager = context.getSystemService(Context.CONNECTIVITY_SERVICE)
            as? ConnectivityManager ?: return false

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            val network = connectivityManager.activeNetwork ?: return false
            val capabilities = connectivityManager.getNetworkCapabilities(network)
            return capabilities?.hasCapability(NetworkCapabilities.NET_CAPABILITY_INTERNET) == true
        } else {
            @Suppress("DEPRECATION")
            val networkInfo = connectivityManager.activeNetworkInfo
            return networkInfo?.isConnected == true
        }
    }

    /**
     * 检查是否连接 Wi-Fi
     */
    private fun isWifiConnected(): Boolean {
        val connectivityManager = context.getSystemService(Context.CONNECTIVITY_SERVICE)
            as? ConnectivityManager ?: return false

        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
            val network = connectivityManager.activeNetwork ?: return false
            val capabilities = connectivityManager.getNetworkCapabilities(network)
            return capabilities?.hasTransport(NetworkCapabilities.TRANSPORT_WIFI) == true
        } else {
            @Suppress("DEPRECATION")
            val networkInfo = connectivityManager.activeNetworkInfo
            return networkInfo?.type == android.net.ConnectivityManager.TYPE_WIFI &&
                    networkInfo.isConnected
        }
    }

    companion object {
        @Volatile
        private var instance: UpdateManager? = null

        /**
         * 获取单例实例
         */
        fun getInstance(context: Context): UpdateManager {
            return instance ?: synchronized(this) {
                instance ?: UpdateManager(context.applicationContext).also {
                    instance = it
                }
            }
        }
    }
}
