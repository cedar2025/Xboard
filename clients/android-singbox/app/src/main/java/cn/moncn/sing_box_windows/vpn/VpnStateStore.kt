package cn.moncn.sing_box_windows.vpn

import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue

object VpnStateStore {
    var state by mutableStateOf(VpnState.IDLE)
        private set
    var lastError by mutableStateOf<String?>(null)
        private set

    fun update(newState: VpnState, error: String? = null) {
        state = newState
        lastError = error
    }
}
