plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

android {
    namespace = "com.elephantroute"
    compileSdk = flutter.compileSdkVersion
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    kotlinOptions {
        jvmTarget = JavaVersion.VERSION_17.toString()
    }

    defaultConfig {
        // TODO: Specify your own unique Application ID (https://developer.android.com/studio/build/application-id.html).
        applicationId = "com.elephantroute"
        // You can update the following values to match your application needs.
        // For more information, see: https://flutter.dev/to/review-gradle-config.
        minSdk = flutter.minSdkVersion
        targetSdk = flutter.targetSdkVersion
        versionCode = flutter.versionCode
        versionName = flutter.versionName

        // Inject APP_LABEL from environment variable, default to "Elephant VPN"
        val appLabel = System.getenv("APP_LABEL") ?: "Elephant VPN"
        manifestPlaceholders["appLabel"] = appLabel
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.getByName("debug")
            
            // Enable R8 shrinking and obfuscation
            isMinifyEnabled = true
            isShrinkResources = true
            
            proguardFiles(
                getDefaultProguardFile("proguard-android-optimize.txt"),
                "proguard-rules.pro"
            )
        }
    }
}

dependencies {
    // libbox.aar dependency
    // 注意: sing-box-for-android 不通过 Maven 发布，需要本地构建
    implementation(fileTree("libs") { include("*.aar") })
}

flutter {
    source = "../.."
}
