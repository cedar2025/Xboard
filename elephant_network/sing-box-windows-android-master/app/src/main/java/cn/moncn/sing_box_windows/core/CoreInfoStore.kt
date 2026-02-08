package cn.moncn.sing_box_windows.core

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue

object CoreInfoStore {
    var version by mutableStateOf<String?>(null)
        private set

    fun updateVersion(version: String?) {
        this.version = version
    }

    fun reset() {
        version = null
    }
}
