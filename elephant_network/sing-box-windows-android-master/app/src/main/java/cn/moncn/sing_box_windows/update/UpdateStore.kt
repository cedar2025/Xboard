package cn.moncn.sing_box_windows.update

import android.content.Context
import android.content.SharedPreferences
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.State
import androidx.compose.runtime.getValue
import androidx.compose.runtime.setValue
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow

/**
 * 应用更新状态管理
 * 采用单例模式，全局共享更新状态
 */
object UpdateStore {
    private const val PREFS_NAME = "update_settings"
    private const val KEY_WIFI_ONLY = "wifi_only_download"

    private lateinit var prefs: SharedPreferences

    /** 当前更新状态 */
    var state by mutableStateOf<UpdateState>(UpdateState.Idle)
        private set

    /** 状态流，用于监听状态变化 */
    private val _stateFlow = MutableStateFlow<UpdateState>(UpdateState.Idle)
    val stateFlow: StateFlow<UpdateState> = _stateFlow.asStateFlow()

    /** 上次检查更新时间 */
    var lastCheckTime: Long = 0
        private set

    /** 上次检查到的版本标签 */
    var lastKnownVersion: String? = null
        private set

    /** 是否仅在 Wi-Fi 下下载更新 */
    var wifiOnlyDownload by mutableStateOf(true)
        private set

    /**
     * 初始化，加载持久化设置
     */
    fun init(context: Context) {
        prefs = context.getSharedPreferences(PREFS_NAME, Context.MODE_PRIVATE)
        wifiOnlyDownload = prefs.getBoolean(KEY_WIFI_ONLY, true)
    }

    /** 更新状态 */
    fun update(newState: UpdateState) {
        state = newState
        _stateFlow.tryEmit(newState)
    }

    /** 设置检查时间 */
    fun setCheckTime(time: Long = System.currentTimeMillis()) {
        lastCheckTime = time
    }

    /** 设置已知版本 */
    fun setKnownVersion(version: String?) {
        lastKnownVersion = version
    }

    /** 设置 Wi-Fi 下载开关 */
    fun setWifiOnly(enabled: Boolean) {
        wifiOnlyDownload = enabled
        prefs.edit().putBoolean(KEY_WIFI_ONLY, enabled).apply()
    }

    /** 检查是否需要自动更新检查 */
    fun shouldCheckForUpdates(interval: Long): Boolean {
        val now = System.currentTimeMillis()
        return now - lastCheckTime >= interval
    }

    /** 重置为空闲状态 */
    fun reset() {
        update(UpdateState.Idle)
    }

    /** 获取当前状态作为 Compose State */
    fun getState(): State<UpdateState> = mutableStateOf(state)

    /** 判断当前是否正在进行更新操作 */
    val isBusy: Boolean
        get() = state is UpdateState.Checking ||
                state is UpdateState.Downloading

    /** 判断是否有可安装的更新 */
    val hasReadyUpdate: Boolean
        get() = state is UpdateState.ReadyToInstall
}
