# GitHub Secrets 配置指南

## 目标
在 GitHub 上配置签名密钥，使自动化构建时自动对 APK 进行签名。

---

## 配置步骤

### 第一步：获取 Base64 编码的密钥

在项目根目录打开 PowerShell，运行：

```powershell
.\convert-keystore-to-base64.ps1
```

这会生成 `keystore-base64.txt` 文件，里面是 Base64 编码的密钥内容。

---

### 第二步：在 GitHub 上配置 Secrets

#### 1. 打开仓库设置页面

访问：https://github.com/xinggaoya/sing-box-windows-android/settings/secrets/actions

或者：
1. 打开仓库页面
2. 点击 `Settings`（设置）
3. 左侧菜单找到 `Secrets and variables`（密钥和变量）
4. 点击 `Actions`

#### 2. 添加 4 个 Secrets

点击 `New repository secret` 按钮，逐个添加：

| Name | Secret | 说明 |
|------|--------|------|
| `KEYSTORE_BASE64` | 打开 `keystore-base64.txt`，复制全部内容粘贴 | 密钥文件的 Base64 编码 |
| `KEYSTORE_PASSWORD` | `7355608` | 密钥库密码 |
| `KEY_ALIAS` | `release` | 密钥别名 |
| `KEY_PASSWORD` | `7355608` | 密钥密码 |

**添加步骤：**
1. 点击 `New repository secret`
2. Name 填写（如 `KEYSTORE_BASE64`）
3. Secret 填写对应值
4. 点击 `Add secret`

---

### 第三步：启用签名功能

#### 1. 切换到 Variables 标签

在同一个页面，点击 `Variables` 标签。

#### 2. 添加变量

点击 `New repository variable`，添加：

| Name | Value |
|------|-------|
| `SIGNING_ENABLED` | `true` |

**重要：** 这个变量控制是否启用签名，设为 `true` 才会自动签名。

---

## 验证配置

配置完成后，Secrets 列表应该显示：

```
KEYSTORE_BASE64    Updated [时间]
KEYSTORE_PASSWORD  Updated [时间]
KEY_ALIAS          Updated [时间]
KEY_PASSWORD       Updated [时间]
```

Variables 列表应该显示：

```
SIGNING_ENABLED    true
```

---

## 测试自动化签名

### 方法 1：推送现有标签

```bash
git push origin v1.1.0
```

### 方法 2：创建新版本标签

```bash
# 更新 docs/CHANGELOG.md 添加新版本
git add docs/CHANGELOG.md
git commit -m "docs: 更新版本日志"

git tag v1.2.0
git push origin v1.2.0
```

### 查看构建结果

1. 访问：https://github.com/xinggaoya/sing-box-windows-android/actions
2. 点击最新的工作流运行
3. 展开构建步骤，查看 "Sign APKs" 是否成功
4. 构建摘要中应显示 ":white_check_mark: 已签名"

---

## 当前配置状态

您的仓库已经通过 GitHub CLI 配置完成：

:white_check_mark: `KEYSTORE_BASE64` - 已配置
:white_check_mark: `KEYSTORE_PASSWORD` - 已配置 (7355608)
:white_check_mark: `KEY_ALIAS` - 已配置 (release)
:white_check_mark: `KEY_PASSWORD` - 已配置 (7355608)
:white_check_mark: `SIGNING_ENABLED` - 已配置 (true)

**您现在可以直接推送标签来触发自动签名构建！**

---

## 图文说明

### Secrets 配置位置

```
GitHub 仓库页面
    ↓
Settings (设置)
    ↓
Secrets and variables (密钥和变量)
    ↓
Actions (点击进入)
    ↓
New repository secret (新建密钥)
```

### 添加 Secret 示例

```
Name:     KEYSTORE_PASSWORD
Secret:   7355608
         ↓
[Add secret] (点击添加)
```

### Variables 配置位置

```
GitHub 仓库页面
    ↓
Settings (设置)
    ↓
Secrets and variables (密钥和变量)
    ↓
Actions (点击进入)
    ↓
Variables 标签 (切换到变量标签)
    ↓
New repository variable (新建变量)
```

---

## 常见问题

### Q: 如何查看已配置的 Secrets？

A: Secrets 是隐藏的，只能看到名称和更新时间，无法查看内容。这是为了安全。

### Q: 如何修改 Secret？

A:
1. 点击 Secret 名称旁边的 `Update` 按钮
2. 输入新值
3. 点击 `Update secret`

### Q: 如何禁用签名？

A: 将 `SIGNING_ENABLED` 变量改为 `false` 或删除它。

### Q: 密钥泄露怎么办？

A:
1. 立即在 GitHub 删除所有相关 Secrets
2. 重新生成新的密钥文件
3. 重新配置 Secrets

---

## 快速命令

```bash
# 生成密钥（如果还没生成）
.\generate-keystore.ps1

# 转换为 Base64
.\convert-keystore-to-base64.ps1

# 查看 Base64 内容
cat keystore-base64.txt

# 测试推送标签
git push origin v1.1.0
```
