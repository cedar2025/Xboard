plugins {
    id("com.android.application")
    id("kotlin-android")
    // The Flutter Gradle Plugin must be applied after the Android and Kotlin Gradle plugins.
    id("dev.flutter.flutter-gradle-plugin")
}

val elephantAndroidAbis = (
    providers.gradleProperty("elephant.androidAbis").orNull
        ?: System.getenv("ELEPHANT_ANDROID_ABIS")
        ?: "arm64-v8a"
).split(",")
    .map { it.trim() }
    .filter { it.isNotEmpty() }
val allLibboxAbis = listOf("armeabi-v7a", "arm64-v8a", "x86", "x86_64")
val excludedLibboxAbis = allLibboxAbis.filterNot { it in elephantAndroidAbis }

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

        // Keep the repository's libbox.aar intact, but package only the ABI requested
        // for this Android build. Override with ELEPHANT_ANDROID_ABIS if needed.
        ndk {
            abiFilters += elephantAndroidAbis
        }
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

    packaging {
        jniLibs {
            excludes += excludedLibboxAbis.map { "lib/$it/**" }
        }
        resources {
            excludes += listOf("assets/flutter_assets/assets/bin/**")
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

tasks.configureEach {
    if (name.startsWith("compileFlutterBuild")) {
        doLast {
            val variantName = name.removePrefix("compileFlutterBuild")
            val variantDirectory = variantName.replaceFirstChar { it.lowercase() }
            val flutterAssetsDir = layout.buildDirectory
                .dir("intermediates/flutter/$variantDirectory/flutter_assets")
                .get()
                .asFile

            delete(flutterAssetsDir.resolve("assets/bin"))
        }
    } else if (name.startsWith("merge") && name.endsWith("Assets")) {
        doLast {
            val variantName = name.removePrefix("merge").removeSuffix("Assets")
            val variantDirectory = variantName.replaceFirstChar { it.lowercase() }
            val mergedAssetsDir = layout.buildDirectory
                .dir("intermediates/assets/$variantDirectory/$name")
                .get()
                .asFile

            delete(mergedAssetsDir.resolve("flutter_assets/assets/bin"))
        }
    }
}
