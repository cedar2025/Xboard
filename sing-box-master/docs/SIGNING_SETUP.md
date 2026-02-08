# APK 签名配置指南

本指南将帮助您配置 Android APK 自动签名功能。

## 配置步骤概览

```
1. 生成签名密钥
2. 转换为 Base64
3. 配置 GitHub Secrets
4. 启用签名功能
```

---

## 步骤 1：生成签名密钥

### 方法 A：使用 PowerShell 脚本（推荐）

在项目根目录打开 PowerShell，运行：

```powershell
.\generate-keystore.ps1
```

**脚本会自动：**
- 在 `~/.android/` 目录生成密钥文件
- 使用预配置的信息：
  - 别名: `release`
  - 密码: `7355608`
  - 有效期: 10000 天

### 方法 B：手动使用 keytool

如果已安装 Android Studio 或 JDK：

```powershell
keytool -genkey -v `
  -keystore "$env:USERPROFILE\.android\singbox-windows-release.jks" `
  -alias release `
  -keyalg RSA `
  -keysize 2048 `
  -validity 10000 `
  -storepass 7355608 `
  -keypass 7355608 `
  -dname "CN=SingBox Windows, OU=Development, O=MonCN, C=CN"
```

### 方法 C：使用 Android Studio

1. 打开 Android Studio
2. 菜单：`Build` → `Generate Signed Bundle/APK`
3. 选择 `APK`，点击 `Next`
4. 点击 `Create new...` 创建新密钥
5. 填写信息：
   - Key store path: `~/.android/singbox-windows-release.jks`
   - Passwords: `7355608`
   - Key alias: `release`
   - Validity: 10000 年

---

## 步骤 2：转换为 Base64

### 使用 PowerShell 脚本（推荐）

```powershell
.\convert-keystore-to-base64.ps1
```

脚本会输出 Base64 编码的密钥内容，并自动复制到剪贴板。

### 手动转换

```powershell
$bytes = [IO.File]::ReadAllBytes("$env:USERPROFILE\.android\singbox-windows-release.jks")
$base64 = [Convert]::ToBase64String($bytes)
$base64  # 这会显示 Base64 内容
```

---

## 步骤 3：配置 GitHub Secrets

1. 打开 GitHub 仓库页面
2. 进入：`Settings` → `Secrets and variables` → `Actions`
3. 点击 `New repository secret` 添加以下 4 个 Secrets：

| Secret 名称 | 值 | 说明 |
|------------|-----|------|
| `KEYSTORE_BASE64` | (步骤 2 输出的 Base64 内容) | 密钥文件的 Base64 编码 |
| `KEYSTORE_PASSWORD` | `7355608` | 密钥库密码 |
| `KEY_ALIAS` | `release` | 密钥别名 |
| `KEY_PASSWORD` | `7355608` | 密钥密码 |

**注意：** `KEYSTORE_BASE64` 是一长串 Base64 字符，请确保复制完整内容。

---

## 步骤 4：启用签名功能

### 方法 A：使用 GitHub CLI（推荐）

```bash
gh repo variable set SIGNING_ENABLED --body "true"
```

### 方法 B：通过 GitHub 网页界面

1. 打开 GitHub 仓库页面
2. 进入：`Settings` → `Secrets and variables` → `Actions` → `Variables` 标签
3. 点击 `New repository variable`
4. 名称: `SIGNING_ENABLED`
5. 值: `true`

---

## 测试配置

配置完成后，推送一个测试标签验证签名是否正常：

```bash
# 创建测试标签
git tag v1.1.1-test
git push origin v1.1.1-test
```

然后在 GitHub Actions 页面查看构建日志：
- 如果看到 "APK 签名完成！" 则配置成功
- 构建摘要中会显示 ":white_check_mark: 已签名"

---

## 密钥安全注意事项

### ⚠️ 重要提醒

1. **不要将密钥文件提交到 Git 仓库**
   - 密钥文件已默认在 `.gitignore` 中排除
   - 如果不小心提交，请立即删除并使用 `git filter-branch` 清理历史

2. **备份密钥文件**
   ```powershell
   # 备份到安全位置
   Copy-Item "$env:USERPROFILE\.android\singbox-windows-release.jks" "D:\Backups\"
   ```

3. **妥善保管密码**
   - 不要在代码中硬编码密码
   - 不要在公开渠道分享密码信息

4. **使用不同的密钥**
   - 开发环境和生产环境使用不同的密钥
   - 每个应用使用独立的密钥

---

## 常见问题

### Q: 忘记密钥密码怎么办？

A: 密钥密码无法恢复，只能重新生成密钥。但重新生成密钥后，应用签名会变化，用户无法直接升级。

### Q: 签名后的 APK 无法安装？

A: 检查以下问题：
- 确认使用的是正确的密钥别名和密码
- 检查 APK 是否被正确签名：`jarsigner -verify -verbose your_app.apk`

### Q: 如何禁用签名功能？

A: 在 GitHub 仓库设置中：
1. 进入 `Settings` → `Secrets and variables` → `Actions` → `Variables`
2. 删除或修改 `SIGNING_ENABLED` 为 `false`

### Q: 密钥文件路径？

A: 默认位置：
- Windows: `C:\Users\你的用户名\.android\singbox-windows-release.jks`
- macOS/Linux: `~/.android/singbox-windows-release.jks`

---

## 配置文件说明

### generate-keystore.ps1
密钥生成脚本，自动创建 Android 签名密钥。

### convert-keystore-to-base64.ps1
将密钥文件转换为 Base64 编码，用于配置 GitHub Secrets。

### .github/workflows/release.yml
自动化构建工作流，包含签名步骤（第 125-164 行）。

---

## 完成检查清单

- [ ] 已生成密钥文件
- [ ] 已备份密钥文件到安全位置
- [ ] 已转换为 Base64
- [ ] 已配置 4 个 GitHub Secrets
- [ ] 已设置 SIGNING_ENABLED = true
- [ ] 已测试构建并验证签名成功
