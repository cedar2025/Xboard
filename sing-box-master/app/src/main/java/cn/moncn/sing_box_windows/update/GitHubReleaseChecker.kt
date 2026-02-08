package cn.moncn.sing_box_windows.update

import android.content.Context
import android.os.Build
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.Serializable
import kotlinx.serialization.decodeFromString
import kotlinx.serialization.json.Json
import okhttp3.OkHttpClient
import okhttp3.Request
import java.util.concurrent.TimeUnit

/**
 * GitHub Release 版本检查器
 * 通过 GitHub REST API 获取最新 Release 信息，支持加速代理
 */
class GitHubReleaseChecker(
    private val context: Context,
    private val config: UpdateConfig = UpdateConfig()
) {
    private val client = OkHttpClient.Builder()
        .connectTimeout(10, TimeUnit.SECONDS)
        .readTimeout(10, TimeUnit.SECONDS)
        .build()

    private val json = Json {
        ignoreUnknownKeys = true
        isLenient = true
    }

    /**
     * GitHub API 代理
     * 将 GitHub API URL转换为加速代理URL
     */
    private fun getProxyUrl(originalUrl: String): String {
        return if (originalUrl.startsWith("https://api.github.com/")) {
            "https://gh-proxy.com/$originalUrl"
        } else {
            originalUrl
        }
    }

    /**
     * 检查最新版本
     * @param currentVersion 当前应用版本（如 "1.0"）
     * @return 更新检查结果
     */
    suspend fun checkLatest(currentVersion: String): UpdateCheckResult = withContext(Dispatchers.IO) {
        try {
            // 使用代理URL请求 GitHub API
            val proxyUrl = getProxyUrl(config.getLatestReleaseUrl())

            // 请求 GitHub API
            val request = Request.Builder()
                .url(proxyUrl)
                .header("Accept", "application/vnd.github.v3+json")
                .build()

            val response = client.newCall(request).execute()
            if (!response.isSuccessful) {
                return@withContext UpdateCheckResult.failed(
                    "API 请求失败: ${response.code}"
                )
            }

            val body = response.body?.string()
            if (body.isNullOrBlank()) {
                return@withContext UpdateCheckResult.failed("响应内容为空")
            }

            // 解析 API 响应
            val apiRelease = json.decodeFromString<GitHubApiRelease>(body)

            // 获取当前设备架构
            val deviceAbi = getDeviceAbi()

            // 筛选当前架构的 APK 文件
            val matchedAssets = apiRelease.assets
                .filter { it.name.endsWith(".apk") }
                .mapNotNull { asset ->
                    val abi = extractAbiFromFileName(asset.name)
                    if (abi != null) {
                        ReleaseAsset(
                            name = asset.name,
                            downloadUrl = asset.downloadUrl,
                            size = asset.size,
                            abi = abi
                        )
                    } else null
                }
                .filter { it.abi == deviceAbi }

            if (matchedAssets.isEmpty()) {
                return@withContext UpdateCheckResult.failed(
                    "未找到适配当前设备 ($deviceAbi) 的 APK 文件"
                )
            }

            // 构建 Release 信息
            val release = GitHubRelease(
                tagName = apiRelease.tagName,
                name = apiRelease.name,
                body = apiRelease.body ?: "暂无更新说明",
                publishedAt = apiRelease.publishedAt,
                assets = matchedAssets
            )

            // 比较版本
            val latestVersion = normalizeVersion(release.tagName)
            val currentVersionNormalized = normalizeVersion(currentVersion)

            if (compareVersions(latestVersion, currentVersionNormalized) > 0) {
                UpdateCheckResult.hasUpdate(release)
            } else {
                UpdateCheckResult.upToDate()
            }

        } catch (e: Exception) {
            UpdateCheckResult.failed(e.message ?: "未知错误")
        }
    }

    /**
     * 获取当前设备的 CPU 架构
     */
    private fun getDeviceAbi(): String {
        return when (Build.SUPPORTED_ABIS.firstOrNull()) {
            "arm64-v8a" -> "arm64-v8a"
            "armeabi-v7a" -> "armeabi-v7a"
            "x86" -> "x86"
            "x86_64" -> "x86_64"
            else -> "universal"
        }
    }

    /**
     * 从文件名中提取 CPU 架构
     * 支持的命名格式: app-arm64-v8a-release.apk, app-x86_64-release-v1.0.0.apk 等
     */
    private fun extractAbiFromFileName(fileName: String): String? {
        val abis = listOf("arm64-v8a", "armeabi-v7a", "x86", "x86_64", "universal")
        return abis.firstOrNull { abi ->
            fileName.contains("-$abi-") || fileName.contains("_${abi}_")
        }
    }

    /**
     * 规范化版本号用于比较
     * 支持 "v1.0.0" -> "1.0.0", "1.0" -> "1.0.0" 等
     */
    private fun normalizeVersion(version: String): List<Int> {
        // 移除 'v' 前缀
        val cleanVersion = version.removePrefix("v")

        // 分割版本号
        val parts = cleanVersion.split(".")
        val major = parts.getOrNull(0)?.toIntOrNull() ?: 0
        val minor = parts.getOrNull(1)?.toIntOrNull() ?: 0
        val patch = parts.getOrNull(2)?.toIntOrNull() ?: 0

        return listOf(major, minor, patch)
    }

    /**
     * 比较两个版本列表
     * @return >0 表示 version1 更新，=0 表示相等，<0 表示 version2 更新
     */
    private fun compareVersions(v1: List<Int>, v2: List<Int>): Int {
        val maxLength = maxOf(v1.size, v2.size)
        for (i in 0 until maxLength) {
            val a = v1.getOrNull(i) ?: 0
            val b = v2.getOrNull(i) ?: 0
            if (a != b) return a - b
        }
        return 0
    }
}

/**
 * GitHub API Release 响应格式
 */
@Serializable
private data class GitHubApiRelease(
    val tag_name: String,
    val name: String,
    val body: String?,
    val published_at: String,
    val assets: List<GitHubApiAsset>
) {
    val tagName: String get() = tag_name
    val publishedAt: String get() = published_at
}

/**
 * GitHub API Asset 响应格式
 */
@Serializable
private data class GitHubApiAsset(
    val name: String,
    val size: Long,
    val browser_download_url: String
) {
    val downloadUrl: String get() = browser_download_url
}
