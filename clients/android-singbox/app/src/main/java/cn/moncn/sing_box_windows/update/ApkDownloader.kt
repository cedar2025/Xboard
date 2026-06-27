package cn.moncn.sing_box_windows.update

import android.content.Context
import android.util.Log
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.Flow
import kotlinx.coroutines.flow.flow
import kotlinx.coroutines.flow.flowOn
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import java.io.File
import java.io.IOException
import java.util.concurrent.TimeUnit

/**
 * APK 下载器
 * 支持带进度下载，使用 gh-proxy 加速，自动保存到应用私有目录
 */
class ApkDownloader(
    private val context: Context
) {
    private val client = OkHttpClient.Builder()
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .build()

    /**
     * GitHub 下载代理
     * 将 GitHub releases 下载URL转换为加速代理URL
     */
    private fun getProxyUrl(originalUrl: String): String {
        return if (originalUrl.startsWith("https://github.com/")) {
            "https://gh-proxy.com/$originalUrl"
        } else if (originalUrl.startsWith("https://objects.githubusercontent.com/")) {
            "https://gh-proxy.com/https://$originalUrl"
        } else {
            originalUrl
        }
    }

    /**
     * 下载 APK 文件
     * @param asset 要下载的资源
     * @param fileName 保存的文件名（不包含路径）
     * @return Flow 发送下载进度
     */
    fun downloadApk(
        asset: ReleaseAsset,
        fileName: String = "update_${asset.name}"
    ): Flow<DownloadProgress> = flow {
        try {
            // 创建目标文件
            val apkFile = File(context.filesDir, "updates/$fileName")
            apkFile.parentFile?.mkdirs()

            Log.d("ApkDownloader", "Target file: ${apkFile.absolutePath}")

            // 如果文件已存在且大小匹配，直接返回
            if (apkFile.exists() && apkFile.length() == asset.size) {
                Log.d("ApkDownloader", "File already exists with correct size: ${apkFile.length()}")
                emit(DownloadProgress.completed(asset.size))
                return@flow
            }

            // 删除旧文件
            if (apkFile.exists()) {
                apkFile.delete()
                Log.d("ApkDownloader", "Deleted existing file")
            }

            // 使用代理URL加速下载
            val proxyUrl = getProxyUrl(asset.downloadUrl)
            Log.d("ApkDownloader", "Download URL: $proxyUrl")

            // 创建请求
            val request = Request.Builder()
                .url(proxyUrl)
                .build()

            Log.d("ApkDownloader", "Starting HTTP request...")
            val response = client.newCall(request).execute()
            Log.d("ApkDownloader", "Response code: ${response.code}, successful: ${response.isSuccessful}")

            if (!response.isSuccessful) {
                throw IOException("下载失败: ${response.code}")
            }

            val responseBody = response.body
                ?: throw IOException("响应内容为空")

            val inputStream = responseBody.byteStream()
            val totalBytes = responseBody.contentLength()

            Log.d("ApkDownloader", "Total bytes: $totalBytes")

            apkFile.outputStream().use { output ->
                inputStream.use { input ->
                    val buffer = ByteArray(8 * 1024) // 8KB 缓冲区
                    var bytesDownloaded = 0L
                    var bytes: Int

                    while (input.read(buffer).also { bytes = it } != -1) {
                        output.write(buffer, 0, bytes)
                        bytesDownloaded += bytes

                        // 发送进度更新
                        val percentage = if (totalBytes > 0) {
                            ((bytesDownloaded * 100) / totalBytes).toInt()
                        } else 0

                        emit(
                            DownloadProgress(
                                bytesDownloaded = bytesDownloaded,
                                totalBytes = totalBytes,
                                percentage = percentage
                            )
                        )
                    }
                    output.flush()
                }
            }

            Log.d("ApkDownloader", "Download completed, file size: ${apkFile.length()}")

        } catch (e: Exception) {
            Log.e("ApkDownloader", "Download error: ${e.message}", e)
            throw IOException("下载失败: ${e.message}", e)
        }
    }.flowOn(Dispatchers.IO)

    /**
     * 获取已下载的 APK 文件
     * @param asset 资源信息
     * @return 文件对象，如果不存在则返回 null
     */
    fun getDownloadedApk(asset: ReleaseAsset): File? {
        val fileName = "update_${asset.name}"
        val apkFile = File(context.filesDir, "updates/$fileName")
        return if (apkFile.exists() && apkFile.length() == asset.size) {
            apkFile
        } else null
    }

    /**
     * 删除已下载的 APK 文件
     */
    suspend fun clearCache() = withContext(Dispatchers.IO) {
        val updateDir = File(context.filesDir, "updates")
        if (updateDir.exists()) {
            updateDir.deleteRecursively()
        }
    }

    /**
     * 获取缓存目录大小
     */
    suspend fun getCacheSize(): Long = withContext(Dispatchers.IO) {
        val updateDir = File(context.filesDir, "updates")
        if (updateDir.exists()) {
            updateDir.walkTopDown()
                .filter { it.isFile }
                .sumOf { it.length() }
        } else 0
    }
}
