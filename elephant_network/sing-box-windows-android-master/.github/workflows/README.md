# GitHub Actions 自动化构建配置说明

本项目使用 GitHub Actions 实现自动化构建 Release APK。

## 工作流程

```
推送 Git 标签 (v1.x.x)
    ↓
触发 GitHub Actions
    ↓
提取 CHANGELOG.md 版本日志
    ↓
构建 Release APK (所有架构)
    ↓
自动创建 GitHub Release
    ↓
上传 APK 文件到 Release
```

## 使用方法

### 1. 发布新版本

完整的发布流程：

```bash
# 1. 更新 docs/CHANGELOG.md，添加新版本日志

# 2. 提交变更
git add docs/CHANGELOG.md
git commit -m "docs: 更新 v1.2.0 版本日志"

# 3. 创建并推送标签（触发自动构建）
git tag v1.2.0
git push origin v1.2.0
```

### 2. 工作流自动执行

推送标签后，GitHub Actions 会自动：
1. 从 `docs/CHANGELOG.md` 提取对应版本的日志内容
2. 构建 Release APK（arm64-v8a, armeabi-v7a, x86, x86_64）
3. 创建 GitHub Release
4. 上传 APK 文件到 Release 页面
5. 生成构建摘要

### 3. 查看构建状态

- 在 GitHub 仓库的 **Actions** 标签页查看工作流运行状态
- 构建完成后，在 **Releases** 页面查看生成的 Release

## CHANGELOG.md 格式规范

为确保正确提取版本日志，请遵循以下格式：

```markdown
# Sing Box Windows - Android VPN 客户端

## v1.2.0 (2025-12-25)

### 主要更新
- 新功能 A
- 新功能 B

### 技术改进
- 改进 X
- 优化 Y

---

## v1.1.0 (2025-12-23)
[之前的版本内容...]
```

**格式要求：**
- 版本标题格式：`## v1.2.0 (日期)` 或 `## v1.2.0`
- 版本之间用 `---` 分隔
- 新版本添加在文件最顶部

## 构建产物

### APK 文件

根据 `splits.abi` 配置，构建生成以下 APK：

| 文件名 | 架构 | 适用设备 |
|--------|------|----------|
| `app-arm64-v8a-release.apk` | ARM 64-bit | 现代手机（推荐） |
| `app-armeabi-v7a-release.apk` | ARM 32-bit | 旧版手机 |
| `app-x86_64-release.apk` | x86-64 | x86 模拟器/设备 |
| `app-x86-release.apk` | x86 | x86 模拟器/设备 |

### 构建产物保留

- GitHub Release：永久保留
- Actions Artifacts：保留 30 天（用于调试）

## APK 签名配置（可选）

如需自动签名 APK，请在 GitHub 仓库配置 Secrets：

### 配置步骤

1. **生成密钥库**
   ```bash
   keytool -genkey -v -keystore release.keystore -alias my-key-alias \
     -keyalg RSA -keysize 2048 -validity 10000
   ```

2. **转换为 Base64**
   ```bash
   # macOS
   base64 -i release.keystore | pbcopy

   # Linux
   base64 -w 0 release.keystore
   ```

3. **添加 GitHub Secrets**

   进入：Settings → Secrets and variables → Actions → New repository secret

   | Secret 名称 | 说明 |
   |------------|------|
   | `KEYSTORE_BASE64` | 密钥库文件的 Base64 编码 |
   | `KEYSTORE_PASSWORD` | 密钥库密码 |
   | `KEY_ALIAS` | 密钥别名 |
   | `KEY_PASSWORD` | 密钥密码 |

4. **启用签名**

   编辑 `.github/workflows/release.yml`，取消注释签名步骤（第 127-140 行）

## 本地构建

```bash
# 构建 Debug 版本
./gradlew assembleDebug

# 构建 Release 版本
./gradlew assembleRelease

# 安装到连接的设备
./gradlew installDebug
```

## 常见问题

### Q: 如何回滚已发布的 Release？

A: 在 Releases 页面删除对应 Release，删除本地标签，重新创建和推送：

```bash
git tag -d v1.2.0
git push origin :refs/tags/v1.2.0
# 修正代码后重新打标签
git tag v1.2.0
git push origin v1.2.0
```

### Q: 构建失败如何处理？

A:
1. 在 Actions 页面查看详细日志
2. 检查 CHANGELOG.md 格式是否正确
3. 确认代码在本地可以成功构建

### Q: 如何修改版本日志格式？

A: 修改 `.github/workflows/release.yml` 中的 `Extract changelog` 步骤（第 44-104 行）

## 工作流文件

- `.github/workflows/release.yml` - 主工作流配置
