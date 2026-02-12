# Repository Guidelines

## 项目结构与模块组织
本仓库为单模块 Android 应用，主模块在 `app/`。核心源码位于 `app/src/main/java/cn/moncn/sing_box_windows/`，按 `config/`、`core/`、`ui/`、`vpn/` 分包组织，入口为 `MainActivity.kt`。界面与资源在 `app/src/main/res/`，本地依赖库 `libbox.aar` 位于 `app/libs/`。测试分别在 `app/src/test/`（单元测试）与 `app/src/androidTest/`（仪器测试）。

## 构建、测试与开发命令
使用 Gradle Wrapper 执行（Windows 用 `gradlew.bat`，macOS/Linux 用 `./gradlew`）：
```bash
./gradlew assembleDebug      # 构建 Debug APK
./gradlew assembleRelease    # 构建 Release APK
./gradlew installDebug       # 安装到已连接设备
./gradlew test               # 运行单元测试（JUnit4）
./gradlew connectedAndroidTest # 运行仪器测试（Espresso/Compose）
./gradlew lint               # 运行 Android Lint
```

## 编码风格与命名规范
语言为 Kotlin + Jetpack Compose。缩进 4 空格，遵循 Kotlin 官方风格与现有代码写法；类/对象用 PascalCase，函数与变量用 camelCase，Compose 组件命名也用 PascalCase。资源文件名使用 `lower_snake_case`。复杂逻辑请添加必要注释，保持注释简洁且有解释性。

## 测试指南
单元测试使用 JUnit4（示例：`ExampleUnitTest.kt`），仪器测试使用 AndroidX JUnit 与 Espresso/Compose 测试框架（示例：`ExampleInstrumentedTest.kt`）。新增业务逻辑尽量补充对应测试；若涉及 UI 行为，优先提供 UI 测试或至少说明验证方式。

## 提交与合并请求规范
Git 历史显示常用提交格式为 `feat(scope): 描述`，建议沿用 Conventional Commits 风格，允许中文描述；无 scope 的简短初始化提交也可接受。PR 需包含变更说明、测试结果与必要截图（UI 改动必需），并关联相关问题或任务编号。

## 配置与安全提示
`local.properties` 保存本机 SDK 路径，不应提交敏感信息。更新 `app/libs/libbox.aar` 时需同步说明版本来源与兼容性影响。若需参考最新依赖或框架文档，优先使用 MCP 获取权威资料以避免过时信息。
