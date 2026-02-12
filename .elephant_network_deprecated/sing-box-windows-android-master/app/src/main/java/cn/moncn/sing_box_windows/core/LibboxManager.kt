package cn.moncn.sing_box_windows.core

import android.content.Context
import io.nekohasekai.libbox.Libbox
import io.nekohasekai.libbox.SetupOptions
import go.Seq
import java.util.Locale

object LibboxManager {
    private var initialized = false

    fun initialize(context: Context) {
        if (initialized) return
        Seq.setContext(context)
        Libbox.setLocale(Locale.getDefault().toLanguageTag().replace("-", "_"))

        val baseDir = context.filesDir.also { it.mkdirs() }
        val workingDir = context.getExternalFilesDir(null)?.also { it.mkdirs() } ?: baseDir
        val tempDir = context.cacheDir.also { it.mkdirs() }
        val options = SetupOptions().apply {
            basePath = baseDir.path
            workingPath = workingDir.path
            tempPath = tempDir.path
            fixAndroidStack = true
        }
        Libbox.setup(options)
        initialized = true
    }
}
