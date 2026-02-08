# Flutter Wrapper
-keep class io.flutter.app.** { *; }
-keep class io.flutter.plugin.**  { *; }
-keep class io.flutter.util.**  { *; }
-keep class io.flutter.view.**  { *; }
-keep class io.flutter.**  { *; }
-keep class io.flutter.plugins.**  { *; }

# Keep Sing-box Libbox (Critical for JNI)
-keep class io.nekohasekai.libbox.** { *; }
-keep interface io.nekohasekai.libbox.** { *; }

# Keep VPN Service and its methods
-keep class com.elphantroute.elephant_network.SingboxVpnService { *; }

# Keep Native Methods
-keepclasseswithmembernames class * {
    native <methods>;
}

# Keep Play Store tasks if used
-dontwarn com.google.android.play.core.**
-dontwarn io.flutter.embedding.engine.deferredcomponents.**

# Keep fastjson/gson/json if used by libbox internally (though it seems to use org.json which is usually safe in android)
-keep class org.json.** { *; }
