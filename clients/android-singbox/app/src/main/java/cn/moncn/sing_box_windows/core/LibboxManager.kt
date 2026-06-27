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
        val workingDir = context.filesDir // FORCE INTERNAL STORAGE
        val tempDir = context.cacheDir.also { it.mkdirs() }
        
        // Copy assets (geoip.db, geosite.db) to working directory
        copyAssets(context, workingDir)

        val options = SetupOptions().apply {
            basePath = baseDir.path
            workingPath = workingDir.path
            tempPath = tempDir.path
            fixAndroidStack = true
        }
        Libbox.setup(options)
        initialized = true
    }

    private fun copyAssets(context: Context, targetDir: java.io.File) {
        try {
            // 1. Copy databases (root assets)
            val dbs = arrayOf("geoip.db", "geosite.db")
            for (filename in dbs) {
                copyAssetFile(context, filename, targetDir)
            }

            // 2. Copy rule-sets (srs folder) directly to root to simplify paths
            val srsFiles = context.assets.list("srs") ?: emptyArray()
            for (filename in srsFiles) {
                copyAssetFile(context, "srs/$filename", targetDir, targetName = filename)
            }
        } catch (e: Exception) {
            e.printStackTrace()
        }
    }

    private fun copyAssetFile(context: Context, assetPath: String, targetDir: java.io.File, targetName: String? = null) {
        val name = targetName ?: java.io.File(assetPath).name
        val targetFile = java.io.File(targetDir, name)
        
        // Simple check: if exists, skip. For production, maybe check version/checksum.
        if (!targetFile.exists()) {
            runCatching {
                context.assets.open(assetPath).use { input ->
                    targetFile.outputStream().use { output ->
                        input.copyTo(output)
                    }
                }
            }.onFailure { it.printStackTrace() }
        }
    }
}
