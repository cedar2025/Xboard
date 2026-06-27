package com.elephantroute

import android.content.Context
import android.util.Log
import io.nekohasekai.libbox.CommandServer
import io.nekohasekai.libbox.CommandServerHandler
import io.nekohasekai.libbox.Libbox
import io.nekohasekai.libbox.PlatformInterface
import io.nekohasekai.libbox.SetupOptions
import io.nekohasekai.libbox.SystemProxyStatus
import java.io.File

object SingBoxEngine : CommandServerHandler {
    private var commandServer: CommandServer? = null
    private var setupDone = false
    private const val TAG = "SingBoxEngine"

    fun start(context: Context, config: String, platform: PlatformInterface) {
        Log.i(TAG, "=== SingBoxEngine.start() called ===")
        Log.d(TAG, "Setup done: $setupDone, CommandServer exists: ${commandServer != null}")
        
        try {
            if (!setupDone) {
                Log.i(TAG, "Preparing to setup Libbox...")
                val options = SetupOptions()
                options.basePath = context.filesDir.absolutePath
                val workingDir = File(context.filesDir, "sing-box")
                if (!workingDir.exists()) {
                    Log.d(TAG, "Creating working directory: ${workingDir.absolutePath}")
                    workingDir.mkdirs()
                }
                options.workingPath = workingDir.absolutePath
                options.tempPath = context.cacheDir.absolutePath
                
                Log.i(TAG, "Calling Libbox.setup() with basePath=${options.basePath}, workingPath=${options.workingPath}")
                Libbox.setup(options)
                setupDone = true
                Log.i(TAG, "✓ Libbox.setup() completed successfully")
            } else {
                Log.d(TAG, "Libbox already set up, skipping setup")
            }

            if (commandServer == null) {
                Log.i(TAG, "Creating CommandServer...")
                Log.d(TAG, "Calling Libbox.newCommandServer(handler, platform)")
                commandServer = Libbox.newCommandServer(this, platform)
                Log.i(TAG, "✓ CommandServer created successfully")
                
                Log.i(TAG, "Starting CommandServer...")
                commandServer?.start()
                Log.i(TAG, "✓ CommandServer started successfully")
            } else {
                Log.d(TAG, "CommandServer already exists, reusing it")
            }
            
            // Start service with config
            Log.i(TAG, "Starting/Reloading service with config (${config.length} bytes)...")
            commandServer?.startOrReloadService(config, null)
            Log.i(TAG, "✓ Service started/reloaded successfully")
            Log.i(TAG, "=== SingBoxEngine.start() completed successfully ===")
        } catch (e: Exception) {
            Log.e(TAG, "❌ FATAL ERROR in SingBoxEngine.start()", e)
            Log.e(TAG, "Error type: ${e.javaClass.name}")
            Log.e(TAG, "Error message: ${e.message}")
            e.printStackTrace()
            throw e
        }
    }

    fun stop() {
        Log.i(TAG, "=== SingBoxEngine.stop() called ===")
        try {
            Log.d(TAG, "Closing service...")
            commandServer?.closeService()
            Log.d(TAG, "✓ Service closed")
        } catch (e: Exception) {
            Log.w(TAG, "Error closing service: ${e.message}")
        }
        
        try {
            Log.d(TAG, "Closing CommandServer...")
            commandServer?.close()
            Log.d(TAG, "✓ CommandServer closed")
        } catch (e: Exception) {
            Log.w(TAG, "Error closing CommandServer: ${e.message}")
        }
        
        commandServer = null
        Log.i(TAG, "=== SingBoxEngine.stop() completed ===")
    }

    // CommandServerHandler implementation
    override fun serviceReload() {
        Log.d(TAG, "serviceReload")
    }
    
    override fun serviceStop() {
        Log.d(TAG, "serviceStop")
        // Not calling stop() here to avoid recursion loop if closeService calls this
        // Just log it. Or maybe we should clear state?
        // Usually initiated by native side stopping.
    }
    
    override fun writeDebugMessage(message: String?) {
        Log.d(TAG, "Debug: $message")
    }

    override fun getSystemProxyStatus(): SystemProxyStatus {
        val status = SystemProxyStatus()
        status.available = false
        status.enabled = false
        return status
    }
    override fun setSystemProxyEnabled(isEnabled: Boolean) {
         Log.d(TAG, "setSystemProxyEnabled: $isEnabled")
    }
}
