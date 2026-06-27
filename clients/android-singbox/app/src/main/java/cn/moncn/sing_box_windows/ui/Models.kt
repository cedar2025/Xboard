package cn.moncn.sing_box_windows.ui

enum class MessageTone {
    Info,
    Success,
    Warning,
    Error
}

data class MessageDialogState(
    val title: String,
    val message: String,
    val tone: MessageTone = MessageTone.Info,
    val confirmText: String = "确定",
    val dismissText: String? = null,
    val onConfirm: (() -> Unit)? = null
)
