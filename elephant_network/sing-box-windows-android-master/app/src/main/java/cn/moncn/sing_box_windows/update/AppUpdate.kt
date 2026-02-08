package cn.moncn.sing_box_windows.update

import android.os.Parcelable
import java.io.File

/**
 * 应用更新状态
 */
sealed class UpdateState {
    /** 空闲状态 - 无更新或检查未开始 */
    data object Idle : UpdateState()

    /** 正在检查更新 */
    data object Checking : UpdateState()

    /** 无可用更新 */
    data object UpToDate : UpdateState()

    /** 发现新版本，等待用户确认 */
    data class UpdateAvailable(
        val releaseInfo: GitHubRelease
    ) : UpdateState()

    /** 正在下载更新 */
    data class Downloading(
        val releaseInfo: GitHubRelease,
        val progress: DownloadProgress
    ) : UpdateState()

    /** 下载完成，等待安装 */
    data class ReadyToInstall(
        val releaseInfo: GitHubRelease,
        val apkFile: File
    ) : UpdateState()

    /** 更新失败 */
    data class Failed(
        val error: String
    ) : UpdateState()
}

/**
 * GitHub Release 信息
 */
data class GitHubRelease(
    /** 版本标签 (如 v1.0.0) */
    val tagName: String,

    /** 版本名称 */
    val name: String,

    /** 发布说明 */
    val body: String,

    /** 发布时间 */
    val publishedAt: String,

    /** APK 资源列表 */
    val assets: List<ReleaseAsset>
)

/**
 * Release 资产文件
 */
data class ReleaseAsset(
    /** 文件名 */
    val name: String,

    /** 下载 URL */
    val downloadUrl: String,

    /** 文件大小（字节） */
    val size: Long,

    /** CPU 架构 */
    val abi: String?
)

/**
 * 下载进度信息
 */
data class DownloadProgress(
    /** 已下载字节数 */
    val bytesDownloaded: Long,

    /** 总字节数 */
    val totalBytes: Long,

    /** 下载进度百分比 (0-100) */
    val percentage: Int
) {
    companion object {
        /** 创建初始进度 */
        fun initial(totalBytes: Long) = DownloadProgress(0, totalBytes, 0)

        /** 创建完成进度 */
        fun completed(totalBytes: Long) = DownloadProgress(totalBytes, totalBytes, 100)
    }

    /** 格式化已下载大小为可读字符串 */
    fun getDownloadedSizeReadable(): String = formatBytes(bytesDownloaded)

    /** 格式化总大小为可读字符串 */
    fun getTotalSizeReadable(): String = formatBytes(totalBytes)

    /** 是否已完成 */
    val isComplete: Boolean get() = bytesDownloaded >= totalBytes

    /** 获取进度字符串 (如 "45%") */
    fun getPercentageString(): String = "${percentage}%"

    private fun formatBytes(bytes: Long): String {
        if (bytes < 1024) return "${bytes}B"
        val kb = bytes / 1024.0
        if (kb < 1024) return "%.1fKB".format(kb)
        val mb = kb / 1024.0
        if (mb < 1024) return "%.1fMB".format(mb)
        val gb = mb / 1024.0
        return "%.2fGB".format(gb)
    }
}

/**
 * 更新检查结果
 */
data class UpdateCheckResult(
    /** 是否有新版本 */
    val hasUpdate: Boolean,

    /** Release 信息（如果有） */
    val release: GitHubRelease?,

    /** 错误信息（如果失败） */
    val error: String? = null
) {
    companion object {
        /** 无更新 */
        fun upToDate() = UpdateCheckResult(false, null)

        /** 有更新 */
        fun hasUpdate(release: GitHubRelease) = UpdateCheckResult(true, release)

        /** 检查失败 */
        fun failed(error: String) = UpdateCheckResult(false, null, error)
    }
}

/**
 * 更新配置
 * 硬编码项目仓库配置，无需用户设置
 */
data class UpdateConfig(
    // 硬编码项目仓库配置
    private val owner: String = "xinggaoya",
    private val repo: String = "sing-box-windows-android",

    /** 是否启用自动检查更新 */
    val autoCheckEnabled: Boolean = true,

    /** 自动检查间隔（毫秒）默认 24 小时 */
    val checkInterval: Long = 24 * 60 * 60 * 1000,

    /** 是否仅在 Wi-Fi 下自动下载 */
    val wifiOnlyDownload: Boolean = true
) {
    /**
     * 获取最新的 Release API 端点
     */
    fun getLatestReleaseUrl(): String = "https://api.github.com/repos/$owner/$repo/releases/latest"
}
