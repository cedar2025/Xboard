window.XBOARD_TRANSLATIONS = window.XBOARD_TRANSLATIONS || {};
window.XBOARD_TRANSLATIONS['zh-CN'] = {
  "payment": {
    "title": "支付配置",
    "description": "在这里可以配置支付方式，包括支付宝、微信等。",
    "table": {
      "columns": {
        "id": "ID",
        "enable": "启用",
        "name": "显示名称",
        "payment": "支付接口",
        "notify_url": "通知地址",
        "notify_url_tooltip": "支付网关将会把数据通知到本地址，请通过防火墙放行本地址。",
        "actions": "操作"
      },
      "actions": {
        "edit": "编辑",
        "delete": {
          "title": "删除确认",
          "description": "确定要删除该支付方式吗？此操作无法撤销。",
          "success": "删除成功"
        }
      },
      "toolbar": {
        "search": "搜索支付方式...",
        "reset": "重置",
        "sort": {
          "hint": "拖拽支付方式进行排序，完成后点击保存",
          "save": "保存排序",
          "edit": "编辑排序"
        }
      }
    },
    "form": {
      "add": {
        "button": "添加支付方式",
        "title": "添加支付方式"
      },
      "edit": {
        "title": "编辑支付方式"
      },
      "fields": {
        "name": {
          "label": "显示名称",
          "placeholder": "请输入支付名称",
          "description": "用于前端显示"
        },
        "icon": {
          "label": "图标URL",
          "placeholder": "https://example.com/icon.svg",
          "description": "用于前端显示的图标地址"
        },
        "notify_domain": {
          "label": "通知域名",
          "placeholder": "https://example.com",
          "description": "网关通知将发送到该域名"
        },
        "handling_fee_percent": {
          "label": "百分比手续费(%)",
          "placeholder": "0-100"
        },
        "handling_fee_fixed": {
          "label": "固定手续费",
          "placeholder": "0"
        },
        "payment": {
          "label": "支付接口",
          "placeholder": "请选择支付接口",
          "description": "选择要使用的支付接口"
        }
      },
      "validation": {
        "name": {
          "min": "名称至少需要2个字符",
          "max": "名称不能超过30个字符"
        },
        "notify_domain": {
          "url": "请输入有效的URL"
        },
        "payment": {
          "required": "请选择支付接口"
        }
      },
      "buttons": {
        "cancel": "取消",
        "submit": "提交"
      },
      "messages": {
        "success": "保存成功"
      }
    }
  },
  "knowledge": {
    "title": "知识库管理",
    "description": "在这里可以配置知识库，包括添加、删除、编辑等操作。",
    "columns": {
      "id": "ID",
      "status": "状态",
      "title": "标题",
      "category": "分类",
      "actions": "操作"
    },
    "form": {
      "add": "添加知识",
      "edit": "编辑知识",
      "title": "标题",
      "titlePlaceholder": "请输入知识标题",
      "category": "分类",
      "categoryPlaceholder": "请输入分类，分类将会自动归类",
      "language": "语言",
      "languagePlaceholder": "请选择语言",
      "content": "内容",
      "show": "显示",
      "cancel": "取消",
      "submit": "提交"
    },
    "languages": {
      "en-US": "English",
      "ja-JP": "日本語",
      "ko-KR": "한국어",
      "vi-VN": "Tiếng Việt",
      "zh-CN": "简体中文",
      "zh-TW": "繁體中文"
    },
    "messages": {
      "deleteConfirm": "确认删除",
      "deleteDescription": "此操作将永久删除该知识库记录，删除后无法恢复。确定要继续吗？",
      "deleteButton": "删除",
      "operationSuccess": "操作成功"
    },
    "toolbar": {
      "searchPlaceholder": "搜索知识...",
      "reset": "重置",
      "sortModeHint": "拖拽知识条目进行排序，完成后点击保存",
      "editSort": "编辑排序",
      "saveSort": "保存排序"
    }
  },
  "search": {
    "placeholder": "搜索菜单和功能...",
    "title": "菜单导航",
    "noResults": "未找到结果",
    "shortcut": {
      "label": "搜索",
      "key": "⌘K"
    }
  },
  "nav": {
    "dashboard": "仪表盘",
    "systemManagement": "系统管理",
    "systemConfig": "系统配置",
    "themeConfig": "主题配置",
    "pluginManagement": "插件管理",
    "noticeManagement": "公告管理",
    "paymentConfig": "支付配置",
    "knowledgeManagement": "知识库管理",
    "nodeManagement": "节点管理",
    "permissionGroupManagement": "权限组管理",
    "routeManagement": "路由管理",
    "subscriptionManagement": "订阅管理",
    "planManagement": "套餐管理",
    "orderManagement": "订单管理",
    "couponManagement": "优惠券管理",
    "userManagement": "用户管理",
    "ticketManagement": "工单管理"
  },
  "plugin": {
    "title": "插件管理",
    "description": "管理和配置系统插件",
    "search": {
      "placeholder": "搜索插件名称或描述..."
    },
    "category": {
      "placeholder": "选择分类",
      "all": "全部",
      "other": "其他"
    },
    "tabs": {
      "all": "所有插件",
      "installed": "已安装",
      "available": "可用"
    },
    "status": {
      "enabled": "已启用",
      "disabled": "已禁用"
    },
    "button": {
      "install": "安装",
      "config": "配置",
      "enable": "启用",
      "disable": "禁用",
      "uninstall": "卸载"
    },
    "upload": {
      "button": "上传插件",
      "title": "上传插件",
      "description": "上传插件包 (.zip)",
      "dragText": "拖拽插件包到此处，或",
      "clickText": "浏览",
      "supportText": "仅支持 .zip 格式文件",
      "uploading": "上传中...",
      "error": {
        "format": "仅支持 .zip 格式文件"
      }
    },
    "delete": {
      "title": "删除插件",
      "description": "确定要删除此插件吗？此操作无法撤销。",
      "button": "删除"
    },
    "uninstall": {
      "title": "卸载插件",
      "description": "确定要卸载此插件吗？卸载后插件数据将被清除。",
      "button": "卸载"
    },
    "config": {
      "title": "配置",
      "description": "修改插件配置",
      "save": "保存",
      "cancel": "取消"
    },
    "author": "作者",
    "messages": {
      "installSuccess": "插件安装成功",
      "installError": "插件安装失败",
      "uninstallSuccess": "插件卸载成功",
      "uninstallError": "插件卸载失败",
      "enableSuccess": "插件启用成功",
      "enableError": "插件启用失败",
      "disableSuccess": "插件禁用成功",
      "disableError": "插件禁用失败",
      "configLoadError": "加载插件配置失败",
      "configSaveSuccess": "配置保存成功",
      "configSaveError": "配置保存失败",
      "uploadSuccess": "插件上传成功",
      "uploadError": "插件上传失败",
      "deleteSuccess": "插件删除成功",
      "deleteError": "插件删除失败"
    }
  },
  "settings": {
    "title": "系统设置",
    "description": "管理系统核心配置，包括站点、安全、订阅、邀请佣金、节点、邮件和通知等设置",
    "site": {
      "title": "站点设置",
      "description": "配置站点基本信息，包括站点名称、描述、货币单位等核心设置。",
      "form": {
        "siteName": {
          "label": "站点名称",
          "placeholder": "请输入站点名称",
          "description": "用于显示需要站点名称的地方。"
        },
        "siteDescription": {
          "label": "站点描述",
          "placeholder": "请输入站点描述",
          "description": "用于显示需要站点描述的地方。"
        },
        "siteUrl": {
          "label": "站点网址",
          "placeholder": "请输入站点URL，末尾不要/",
          "description": "当前网站最新网址，将会在邮件等需要用于网址处体现。"
        },
        "forceHttps": {
          "label": "强制HTTPS",
          "description": "当站点没有使用HTTPS，CDN或反代开启强制HTTPS时需要开启。"
        },
        "logo": {
          "label": "LOGO",
          "placeholder": "请输入LOGO URL，末尾不要/",
          "description": "用于显示需要LOGO的地方。"
        },
        "subscribeUrl": {
          "label": "订阅URL",
          "placeholder": "用于订阅所使用，多个订阅地址用','隔开.留空则为站点URL。",
          "description": "用于订阅所使用，留空则为站点URL。"
        },
        "tosUrl": {
          "label": "用户条款(TOS)URL",
          "placeholder": "请输入用户条款URL，末尾不要/",
          "description": "用于跳转到用户条款(TOS)"
        },
        "stopRegister": {
          "label": "停止新用户注册",
          "description": "开启后任何人都将无法进行注册。"
        },
        "tryOut": {
          "label": "注册试用",
          "placeholder": "关闭",
          "description": "选择需要试用的订阅，如果没有选项请先前往订阅管理添加。",
          "duration": {
            "label": "注册试用时长",
            "placeholder": "0",
            "description": "注册试用时长，单位为小时。"
          }
        },
        "currency": {
          "label": "货币单位",
          "placeholder": "CNY",
          "description": "仅用于展示使用，更改后系统中所有的货币单位都将发生变更。"
        },
        "currencySymbol": {
          "label": "货币符号",
          "placeholder": "¥",
          "description": "仅用于展示使用，更改后系统中所有的货币单位都将发生变更。"
        }
      }
    },
    "safe": {
      "title": "安全设置",
      "description": "配置系统安全相关选项，包括登录验证、密码策略、API访问等安全设置。",
      "form": {
        "emailVerify": {
          "label": "邮箱验证",
          "description": "开启后将会强制要求用户进行邮箱验证。"
        },
        "gmailLimit": {
          "label": "禁止使用Gmail多别名",
          "description": "开启后Gmail多别名将无法注册。"
        },
        "safeMode": {
          "label": "安全模式",
          "description": "开启后除了站点URL以外的绑定本站点的域名访问都将会被403。"
        },
        "securePath": {
          "label": "后台路径",
          "placeholder": "admin",
          "description": "后台管理路径，修改后将会改变原有的admin路径"
        },
        "emailWhitelist": {
          "label": "邮箱后缀白名单",
          "description": "开启后在名单中的邮箱后缀才允许进行注册。",
          "suffixes": {
            "label": "邮箱后缀",
            "placeholder": "输入邮箱后缀，每行一个",
            "description": "输入允许的邮箱后缀，每行一个"
          }
        },
        "recaptcha": {
          "enable": {
            "label": "启用reCAPTCHA",
            "description": "开启后用户注册时需要通过reCAPTCHA验证。"
          },
          "key": {
            "label": "reCAPTCHA密钥",
            "placeholder": "输入reCAPTCHA密钥",
            "description": "输入您的reCAPTCHA密钥"
          },
          "siteKey": {
            "label": "reCAPTCHA站点密钥",
            "placeholder": "输入reCAPTCHA站点密钥",
            "description": "输入您的reCAPTCHA站点密钥"
          }
        },
        "registerLimit": {
          "enable": {
            "label": "IP注册限制",
            "description": "开启后将限制同一IP的注册次数。"
          },
          "count": {
            "label": "注册次数",
            "placeholder": "输入最大注册次数",
            "description": "同一IP允许的最大注册次数"
          },
          "expire": {
            "label": "限制时长",
            "placeholder": "输入限制时长（小时）",
            "description": "注册限制的持续时间（小时）"
          }
        },
        "passwordLimit": {
          "enable": {
            "label": "密码尝试限制",
            "description": "开启后将限制密码尝试次数。"
          },
          "count": {
            "label": "尝试次数",
            "placeholder": "输入最大尝试次数",
            "description": "允许的最大密码尝试次数"
          },
          "expire": {
            "label": "锁定时长",
            "placeholder": "输入锁定时长（小时）",
            "description": "账户锁定的持续时间（小时）"
          }
        }
      }
    },
    "subscribe": {
      "title": "订阅设置",
      "description": "管理用户订阅相关配置，包括订阅链接格式、更新频率、流量统计等设置。",
      "plan_change_enable": {
        "title": "允许用户更改订阅",
        "description": "开启后用户将会可以对订阅计划进行变更。"
      },
      "reset_traffic_method": {
        "title": "月流量重置方式",
        "description": "全局流量重置方式，默认每月1号。可以在订阅管理为订阅单独设置。",
        "options": {
          "monthly_first": "每月1号",
          "monthly_reset": "按月重置",
          "no_reset": "不重置",
          "yearly_first": "每年1月1号",
          "yearly_reset": "按年重置"
        }
      },
      "surplus_enable": {
        "title": "开启折抵方案",
        "description": "开启后用户更换订阅将会由系统对原有订阅进行折抵，方案参考文档。"
      },
      "new_order_event": {
        "title": "当订阅新购时触发事件",
        "description": "新购订阅完成时将触发该任务。",
        "options": {
          "no_action": "不执行任何动作",
          "reset_traffic": "重置用户流量"
        }
      },
      "renew_order_event": {
        "title": "当订阅续费时触发事件",
        "description": "续费订阅完成时将触发该任务。",
        "options": {
          "no_action": "不执行任何动作",
          "reset_traffic": "重置用户流量"
        }
      },
      "change_order_event": {
        "title": "当订阅变更时触发事件",
        "description": "变更订阅完成时将触发该任务。",
        "options": {
          "no_action": "不执行任何动作",
          "reset_traffic": "重置用户流量"
        }
      },
      "subscribe_path": {
        "title": "订阅路径",
        "description": "订阅路径，修改后将会改变原有的subscribe路径",
        "current_format": "当前订阅路径格式：{path}/xxxxxxxxxx"
      },
      "show_info_to_server": {
        "title": "在订阅中展示订阅信息",
        "description": "开启后将会在用户订阅节点时输出订阅信息。"
      },
      "show_protocol_to_server": {
        "title": "在订阅中线路名称中显示协议名称",
        "description": "开启后订阅线路会附带协议名称（例如: [Hy2]香港）"
      },
      "saving": "保存中...",
      "plan": {
        "title": "订阅套餐",
        "add": "添加套餐",
        "search": "搜索套餐...",
        "sort": {
          "edit": "编辑排序",
          "save": "保存排序"
        },
        "columns": {
          "id": "ID",
          "show": "显示",
          "sell": "新购",
          "renew": "续费",
          "renew_tooltip": "在订阅停止销售时，已购用户是否可以续费",
          "name": "名称",
          "stats": "统计",
          "group": "权限组",
          "price": "价格",
          "actions": "操作",
          "edit": "编辑",
          "delete": "删除",
          "delete_confirm": {
            "title": "确认删除",
            "description": "此操作将永久删除该订阅，删除后无法恢复。确定要继续吗？",
            "success": "删除成功"
          },
          "price_period": {
            "monthly": "月付",
            "quarterly": "季付",
            "half_yearly": "半年付",
            "yearly": "年付",
            "two_yearly": "两年付",
            "three_yearly": "三年付",
            "onetime": "流量包",
            "reset_traffic": "重置包",
            "unit": {
              "month": "元/月",
              "quarter": "元/季",
              "half_year": "元/半年",
              "year": "元/年",
              "two_year": "元/两年",
              "three_year": "元/三年",
              "times": "元/次"
            }
          }
        },
        "form": {
          "add_title": "添加套餐",
          "edit_title": "编辑套餐",
          "name": {
            "label": "套餐名称",
            "placeholder": "请输入套餐名称"
          },
          "group": {
            "label": "权限组",
            "placeholder": "选择权限组",
            "add": "添加权限组"
          },
          "transfer": {
            "label": "流量",
            "placeholder": "请输入流量大小",
            "unit": "GB"
          },
          "speed": {
            "label": "限速",
            "placeholder": "请输入限速",
            "unit": "Mbps"
          },
          "price": {
            "title": "售价设置",
            "base_price": "基础月付价格",
            "clear": {
              "button": "清空价格",
              "tooltip": "清空所有周期的价格设置"
            }
          },
          "device": {
            "label": "设备限制",
            "placeholder": "留空则不限制",
            "unit": "台"
          },
          "capacity": {
            "label": "容量限制",
            "placeholder": "留空则不限制",
            "unit": "人"
          },
          "reset_method": {
            "label": "流量重置方式",
            "placeholder": "选择流量重置方式",
            "description": "设置订阅流量的重置方式，不同的重置方式会影响用户的流量计算方式",
            "options": {
              "follow_system": "跟随系统设置",
              "monthly_first": "每月1号",
              "monthly_reset": "按月重置",
              "no_reset": "不重置",
              "yearly_first": "每年1月1日",
              "yearly_reset": "按年重置"
            }
          },
          "content": {
            "label": "套餐描述",
            "placeholder": "在这里编写套餐描述...",
            "description": "支持 Markdown 格式，可以使用标题、列表、粗体、斜体等样式来美化描述内容",
            "preview": "预览",
            "preview_button": {
              "show": "显示预览",
              "hide": "隐藏预览"
            },
            "template": {
              "button": "使用模板",
              "tooltip": "点击使用预设的套餐描述模板",
              "content": "## 套餐特点\n• 高速稳定的全球网络接入\n• 支持多设备同时在线\n• 无限制的流量重置\n\n## 使用说明\n1. 支持设备：iOS、Android、Windows、macOS\n2. 24/7 技术支持\n3. 自动定期流量重置\n\n## 注意事项\n- 禁止滥用\n- 遵守当地法律法规\n- 支持随时更换套餐"
            }
          },
          "force_update": {
            "label": "强制更新到用户"
          },
          "submit": {
            "submitting": "提交中...",
            "submit": "提交",
            "cancel": "取消",
            "success": {
              "add": "套餐添加成功",
              "update": "套餐更新成功"
            }
          }
        },
        "page": {
          "description": "在这里可以配置订阅计划，包括添加、删除、编辑等操作。"
        }
      }
    },
    "email": {
      "title": "邮件设置",
      "description": "配置系统邮件服务，用于发送验证码、密码重置、通知等邮件，支持多种SMTP服务商。",
      "email_host": {
        "title": "SMTP主机",
        "description": "SMTP服务器地址，例如：smtp.gmail.com"
      },
      "email_port": {
        "title": "SMTP端口",
        "description": "SMTP服务器端口，常用端口：25, 465, 587"
      },
      "email_username": {
        "title": "SMTP用户名",
        "description": "SMTP认证用户名"
      },
      "email_password": {
        "title": "SMTP密码",
        "description": "SMTP认证密码或应用专用密码"
      },
      "email_encryption": {
        "title": "加密方式",
        "description": "邮件加密方式",
        "none": "无",
        "ssl": "SSL/TLS",
        "tls": "STARTTLS"
      },
      "email_from": {
        "title": "发件人地址",
        "description": "发件人邮箱地址"
      },
      "email_from_name": {
        "title": "发件人名称",
        "description": "发件人显示名称"
      },
      "email_template": {
        "title": "邮件模板",
        "description": "自定义邮件模板方式请查看文档",
        "placeholder": "选择邮件模板"
      },
      "remind_mail": {
        "title": "邮件提醒",
        "description": "开启后用户订阅即将到期或流量不足时会收到邮件通知。"
      },
      "test": {
        "title": "发送测试邮件",
        "sending": "发送中...",
        "description": "发送测试邮件以验证配置",
        "success": "测试邮件发送成功",
        "error": "测试邮件发送失败"
      }
    },
    "telegram": {
      "title": "Telegram设置",
      "description": "配置Telegram机器人功能，实现用户通知、账户绑定、指令交互等自动化服务。",
      "bot_token": {
        "title": "机器人令牌",
        "description": "请输入从Botfather获取的令牌。",
        "placeholder": "0000000000:xxxxxxxxx_xxxxxxxxxxxxxxx"
      },
      "webhook": {
        "title": "设置Webhook",
        "description": "设置机器人的webhook，不设置将无法收到Telegram通知。",
        "button": "一键设置",
        "setting": "设置中...",
        "success": "Webhook 设置成功"
      },
      "bot_enable": {
        "title": "启用机器人通知",
        "description": "开启后机器人将向管理员和已绑定Telegram的用户发送基础通知。"
      },
      "discuss_link": {
        "title": "群组链接",
        "description": "填写后将在用户端显示或在需要的地方使用。",
        "placeholder": "https://t.me/xxxxxx"
      }
    },
    "app": {
      "title": "APP设置",
      "description": "管理移动应用程序相关配置，包括API接口、版本控制、推送通知等功能设置。",
      "common": {
        "placeholder": "请输入"
      },
      "windows": {
        "version": {
          "title": "Windows版本",
          "description": "Windows客户端当前版本号"
        },
        "download": {
          "title": "Windows下载地址",
          "description": "Windows客户端下载链接"
        }
      },
      "macos": {
        "version": {
          "title": "macOS版本",
          "description": "macOS客户端当前版本号"
        },
        "download": {
          "title": "macOS下载地址",
          "description": "macOS客户端下载链接"
        }
      },
      "android": {
        "version": {
          "title": "Android版本",
          "description": "Android客户端当前版本号"
        },
        "download": {
          "title": "Android下载地址",
          "description": "Android客户端下载链接"
        }
      }
    },
    "common": {
      "saving": "保存中...",
      "save_success": "已自动保存",
      "placeholder": "请输入",
      "autoSaved": "已自动保存"
    },
    "invite": {
      "title": "邀请&佣金设置",
      "description": "邀请注册、佣金相关设置。",
      "invite_force": {
        "title": "开启强制邀请",
        "description": "开启后只有被邀请的用户才可以进行注册。"
      },
      "invite_commission": {
        "title": "邀请佣金百分比",
        "description": "默认全局的佣金分配比例，你可以在用户管理单独配置单个比例。",
        "placeholder": "请输入佣金百分比"
      },
      "invite_gen_limit": {
        "title": "用户可创建邀请码上限",
        "description": "用户可创建邀请码上限",
        "placeholder": "请输入创建上限"
      },
      "invite_never_expire": {
        "title": "邀请码永不失效",
        "description": "开启后邀请码被使用后将不会失效，否则使用过后即失效。"
      },
      "commission_first_time": {
        "title": "佣金仅首次发放",
        "description": "开启后被邀请人首次支付时才会产生佣金，可以在用户管理对用户进行单独配置。"
      },
      "commission_auto_check": {
        "title": "佣金自动确认",
        "description": "开启后佣金将会在订单完成3日后自动进行确认。"
      },
      "commission_withdraw_limit": {
        "title": "提现单申请门槛(元)",
        "description": "小于门槛金额的提现单将不会被提交。",
        "placeholder": "请输入提现门槛"
      },
      "commission_withdraw_method": {
        "title": "提现方式",
        "description": "可以支持的提现方式，多个用逗号分隔。",
        "placeholder": "请输入提现方式，多个用逗号分隔"
      },
      "withdraw_close": {
        "title": "关闭提现",
        "description": "关闭后将禁止用户申请提现，且邀请佣金将会直接进入用户余额。"
      },
      "commission_distribution": {
        "title": "三级分销",
        "description": "开启后将佣金将按照设置的3成比例进行分成，三成比例合计请不要大于100%。",
        "l1": "一级邀请人比例",
        "l2": "二级邀请人比例",
        "l3": "三级邀请人比例",
        "placeholder": "请输入比例，如：50"
      },
      "saving": "保存中..."
    },
    "server": {
      "title": "节点配置",
      "description": "配置节点通信和同步设置，包括通信密钥、轮询间隔、负载均衡等高级选项。",
      "server_token": {
        "title": "通讯密钥",
        "description": "Xboard与节点通讯的密钥，以便数据不会被他人获取。",
        "placeholder": "请输入通讯密钥",
        "generate_tooltip": "点击生成随机通信密钥"
      },
      "server_pull_interval": {
        "title": "节点拉取动作轮询间隔",
        "description": "节点从面板获取数据的间隔频率。",
        "placeholder": "请输入拉取间隔"
      },
      "server_push_interval": {
        "title": "节点推送动作轮询间隔",
        "description": "节点推送数据到面板的间隔频率。",
        "placeholder": "请输入推送间隔"
      },
      "device_limit_mode": {
        "title": "设备限制模式",
        "description": "宽松模式下，同一IP地址使用多个节点只统计为一个设备。",
        "strict": "严格模式",
        "relaxed": "宽松模式",
        "placeholder": "请选择设备限制模式"
      },
      "saving": "保存中...",
      "manage": {
        "title": "节点管理",
        "description": "管理所有节点，包括添加、删除、编辑等操作。"
      }
    }
  },
  "group": {
    "title": "权限组管理",
    "description": "管理所有权限组，包括添加、删除、编辑等操作。",
    "columns": {
      "id": "组ID",
      "name": "组名称",
      "usersCount": "用户数量",
      "serverCount": "节点数量",
      "actions": "操作"
    },
    "form": {
      "add": "添加权限组",
      "edit": "编辑权限组",
      "create": "创建权限组",
      "update": "更新",
      "name": "组名称",
      "namePlaceholder": "请输入权限组名称",
      "nameDescription": "权限组名称用于标识不同的用户组，建议使用有意义的名称。",
      "cancel": "取消",
      "editDescription": "修改权限组信息，更新后会立即生效。",
      "createDescription": "创建新的权限组，可以为不同的用户分配不同的权限。"
    },
    "toolbar": {
      "searchPlaceholder": "搜索权限组...",
      "reset": "重置"
    },
    "messages": {
      "deleteConfirm": "确认删除",
      "deleteDescription": "此操作将永久删除该权限组，删除后无法恢复。确定要继续吗？",
      "deleteButton": "删除",
      "createSuccess": "创建成功",
      "updateSuccess": "更新成功",
      "nameValidation": {
        "min": "组名至少需要2个字符",
        "max": "组名不能超过50个字符",
        "pattern": "组名只能包含字母、数字、中文、下划线和连字符"
      }
    }
  },
  "traffic": {
    "trafficRecord": {
      "title": "流量使用记录",
      "time": "时间",
      "upload": "上行流量",
      "download": "下行流量",
      "rate": "倍率",
      "total": "总计",
      "noRecords": "暂无记录",
      "perPage": "每页显示",
      "records": "条记录",
      "page": "第 {{current}} / {{total}} 页",
      "multiplier": "{{value}}x"
    }
  },
  "common": {
    "loading": "加载中...",
    "error": "错误",
    "success": "成功",
    "save": "保存",
    "cancel": "取消",
    "confirm": "确认",
    "delete": {
      "success": "删除成功",
      "failed": "删除失败"
    },
    "edit": "编辑",
    "view": "查看",
    "toggleNavigation": "切换导航",
    "toggleSidebar": "切换侧边栏",
    "search": "搜索...",
    "theme": {
      "light": "浅色",
      "dark": "深色",
      "system": "跟随系统"
    },
    "user": "用户",
    "defaultEmail": "user@example.com",
    "settings": "设置",
    "logout": "退出登录",
    "copy": {
      "success": "复制成功",
      "failed": "复制失败",
      "error": "复制失败",
      "errorLog": "复制到剪贴板时出错"
    },
    "table": {
      "noData": "暂无数据",
      "pagination": {
        "selected": "已选择 {{selected}} 项，共 {{total}} 项",
        "itemsPerPage": "每页显示",
        "page": "第",
        "pageOf": "页，共 {{total}} 页",
        "firstPage": "跳转到第一页",
        "previousPage": "上一页",
        "nextPage": "下一页",
        "lastPage": "跳转到最后一页"
      }
    },
    "update": {
      "title": "系统更新",
      "newVersion": "发现新版本",
      "currentVersion": "当前版本",
      "latestVersion": "最新版本",
      "updateLater": "稍后更新",
      "updateNow": "立即更新",
      "updating": "更新中...",
      "updateSuccess": "更新成功，系统将在稍后自动重启",
      "updateFailed": "更新失败，请稍后重试"
    }
  },
  "dashboard": {
    "title": "仪表盘",
    "stats": {
      "newUsers": "新用户",
      "totalScore": "总积分",
      "monthlyUpload": "月上传",
      "vsLastMonth": "对比上月",
      "vsYesterday": "对比昨日",
      "todayIncome": "今日收入",
      "monthlyIncome": "月收入",
      "totalIncome": "总收入",
      "totalUsers": "总用户",
      "activeUsers": "活跃用户: {{count}}",
      "totalOrders": "总订单",
      "revenue": "收入",
      "todayRegistered": "今日注册",
      "monthlyRegistered": "月注册",
      "onlineUsers": "在线用户",
      "pendingTickets": "待处理工单",
      "hasPendingTickets": "有工单需要处理",
      "noPendingTickets": "无待处理工单",
      "pendingCommission": "待处理佣金",
      "hasPendingCommission": "有佣金需要确认",
      "noPendingCommission": "无待处理佣金",
      "monthlyNewUsers": "月新增用户",
      "monthlyDownload": "月下载",
      "todayTraffic": "今日: {{value}}",
      "activeUserTrend": "活跃用户趋势",
      "realtimeUsers": "实时用户",
      "todayPeak": "今日峰值",
      "vsLastWeek": "对比上周"
    },
    "trafficRank": {
      "nodeTrafficRank": "节点流量排行",
      "userTrafficRank": "用户流量排行",
      "today": "今天",
      "last7days": "最近7天",
      "last30days": "最近30天",
      "customRange": "自定义范围",
      "selectTimeRange": "选择时间范围",
      "selectDateRange": "选择日期范围",
      "currentTraffic": "当前流量",
      "previousTraffic": "上期流量",
      "changeRate": "变化率",
      "recordTime": "记录时间"
    },
    "overview": {
      "title": "收入概览",
      "thisMonth": "本月",
      "lastMonth": "上月",
      "to": "至",
      "selectTimeRange": "选择范围",
      "selectDate": "选择日期",
      "last7Days": "最近7天",
      "last30Days": "最近30天",
      "last90Days": "最近90天",
      "last180Days": "最近180天",
      "lastYear": "最近一年",
      "customRange": "自定义范围",
      "amount": "金额",
      "count": "数量",
      "transactions": "{{count}} 笔交易",
      "orderAmount": "订单金额",
      "commissionAmount": "佣金金额",
      "orderCount": "订单数量",
      "commissionCount": "佣金数量",
      "totalIncome": "总收入",
      "totalCommission": "总佣金",
      "totalTransactions": "共 {{count}} 笔交易",
      "avgOrderAmount": "平均订单金额:",
      "commissionRate": "佣金比例:"
    },
    "queue": {
      "title": "队列状态",
      "jobDetails": "作业详情",
      "status": {
        "description": "当前队列运行状态",
        "running": "运行状态",
        "normal": "正常",
        "abnormal": "异常",
        "waitTime": "当前等待时间：{{seconds}} 秒",
        "pending": "等待中",
        "processing": "处理中",
        "completed": "已完成",
        "failed": "失败",
        "cancelled": "已取消"
      },
      "details": {
        "description": "队列处理详细信息",
        "recentJobs": "近期任务数",
        "statisticsPeriod": "统计时间范围：{{hours}} 小时",
        "jobsPerMinute": "每分钟处理量",
        "maxThroughput": "最高吞吐量：{{value}}",
        "failedJobs7Days": "7日报错数量",
        "retentionPeriod": "保留 {{hours}} 小时",
        "longestRunningQueue": "最长运行队列",
        "activeProcesses": "活跃进程",
        "id": "作业ID",
        "type": "作业类型",
        "status": "状态",
        "progress": "进度",
        "createdAt": "创建时间",
        "updatedAt": "更新时间",
        "error": "错误信息",
        "data": "作业数据",
        "result": "结果",
        "duration": "耗时",
        "attempts": "重试次数",
        "nextRetry": "下次重试"
      },
      "actions": {
        "retry": "重试",
        "cancel": "取消",
        "delete": "删除",
        "viewDetails": "查看详情"
      },
      "empty": "队列中暂无作业",
      "loading": "正在加载队列状态...",
      "error": "加载队列状态失败"
    }
  },
  "route": {
    "title": "路由管理",
    "description": "管理所有路由组，包括添加、删除、编辑等操作。",
    "columns": {
      "id": "组ID",
      "remarks": "备注",
      "action": "动作",
      "actions": "操作",
      "matchRules": "匹配{{count}}条规则"
    },
    "actions": {
      "dns": "指定DNS服务器进行解析",
      "block": "禁止访问"
    },
    "form": {
      "add": "添加路由",
      "edit": "编辑路由",
      "create": "创建路由",
      "remarks": "备注",
      "remarksPlaceholder": "请输入备注",
      "match": "匹配规则",
      "matchPlaceholder": "example.com\n*.example.com",
      "action": "动作",
      "actionPlaceholder": "请选择动作",
      "dns": "DNS服务器",
      "dnsPlaceholder": "请输入DNS服务器",
      "cancel": "取消",
      "submit": "提交",
      "validation": {
        "remarks": "请输入有效的备注"
      }
    },
    "toolbar": {
      "searchPlaceholder": "搜索路由...",
      "reset": "重置"
    },
    "messages": {
      "deleteConfirm": "确认删除",
      "deleteDescription": "此操作将永久删除该路由组，删除后无法恢复。确定要继续吗？",
      "deleteButton": "删除",
      "deleteSuccess": "删除成功",
      "createSuccess": "创建成功",
      "updateSuccess": "更新成功"
    }
  },
  "order": {
    "title": "订单管理",
    "description": "在这里可以查看用户订单，包括分配、查看、删除等操作。",
    "table": {
      "columns": {
        "tradeNo": "订单号",
        "type": "类型",
        "plan": "订阅计划",
        "period": "周期",
        "amount": "支付金额",
        "status": "订单状态",
        "commission": "佣金金额",
        "commissionStatus": "佣金状态",
        "createdAt": "创建时间"
      }
    },
    "type": {
      "NEW": "新购",
      "RENEWAL": "续费",
      "UPGRADE": "升级",
      "RESET_FLOW": "流量重置"
    },
    "period": {
      "month_price": "月付",
      "quarter_price": "季付",
      "half_year_price": "半年付",
      "year_price": "年付",
      "two_year_price": "两年付",
      "three_year_price": "三年付",
      "onetime_price": "一次性",
      "reset_price": "流量重置包"
    },
    "status": {
      "PENDING": "待支付",
      "PROCESSING": "开通中",
      "CANCELLED": "已取消",
      "COMPLETED": "已完成",
      "DISCOUNTED": "已折抵",
      "tooltip": "标记为[已支付]后将会由系统进行开通后并完成"
    },
    "commission": {
      "PENDING": "待确认",
      "PROCESSING": "发放中",
      "VALID": "有效",
      "INVALID": "无效"
    },
    "actions": {
      "markAsPaid": "标记为已支付",
      "cancel": "取消订单",
      "openMenu": "打开菜单",
      "reset": "重置"
    },
    "search": {
      "placeholder": "搜索订单..."
    },
    "dialog": {
      "title": "订单信息",
      "basicInfo": "基本信息",
      "amountInfo": "金额信息",
      "timeInfo": "时间信息",
      "addOrder": "添加订单",
      "assignOrder": "订单分配",
      "fields": {
        "userEmail": "用户邮箱",
        "orderPeriod": "订单周期",
        "subscriptionPlan": "订阅计划",
        "callbackNo": "回调单号",
        "paymentAmount": "支付金额",
        "balancePayment": "余额支付",
        "discountAmount": "优惠金额",
        "refundAmount": "退回金额",
        "deductionAmount": "折抵金额",
        "createdAt": "创建时间",
        "updatedAt": "更新时间"
      },
      "placeholders": {
        "email": "请输入用户邮箱",
        "plan": "请选择订阅计划",
        "period": "请选择购买时长",
        "amount": "请输入需要支付的金额"
      },
      "actions": {
        "cancel": "取消",
        "confirm": "确定"
      },
      "messages": {
        "addSuccess": "添加成功"
      }
    }
  },
  "coupon": {
    "title": "优惠券管理",
    "description": "在这里可以查看优惠券，包括增加、查看、删除等操作。",
    "table": {
      "columns": {
        "id": "ID",
        "show": "启用",
        "name": "卷名称",
        "type": "类型",
        "code": "卷码",
        "limitUse": "剩余次数",
        "limitUseWithUser": "可用次数/用户",
        "validity": "有效期",
        "actions": "操作"
      },
      "validity": {
        "expired": "已过期{{days}}天",
        "notStarted": "{{days}}天后开始",
        "remaining": "剩余{{days}}天",
        "startTime": "开始时间",
        "endTime": "结束时间",
        "unlimited": "无限次",
        "noLimit": "无限制"
      },
      "actions": {
        "edit": "编辑",
        "delete": "删除",
        "deleteConfirm": {
          "title": "确认删除",
          "description": "此操作将永久删除该优惠券，删除后无法恢复。确定要继续吗？",
          "confirmText": "删除"
        }
      },
      "toolbar": {
        "search": "搜索优惠券...",
        "type": "类型",
        "reset": "重置",
        "types": {
          "1": "按金额优惠",
          "2": "按比例优惠"
        }
      }
    },
    "form": {
      "add": "添加优惠券",
      "edit": "编辑优惠券",
      "name": {
        "label": "优惠券名称",
        "placeholder": "请输入优惠券名称",
        "required": "请输入优惠券名称"
      },
      "type": {
        "label": "优惠券类型和值",
        "placeholder": "优惠券类型"
      },
      "value": {
        "placeholder": "请输入值"
      },
      "validity": {
        "label": "优惠券有效期",
        "to": "至",
        "endTimeError": "结束时间必须晚于开始时间"
      },
      "limitUse": {
        "label": "最大使用次数",
        "placeholder": "限制最大使用次数，留空则不限制",
        "description": "设置优惠券的总使用次数限制，留空表示不限制使用次数"
      },
      "limitUseWithUser": {
        "label": "每个用户可使用次数",
        "placeholder": "限制每个用户可使用次数，留空则不限制",
        "description": "限制每个用户可使用该优惠券的次数，留空表示不限制单用户使用次数"
      },
      "limitPeriod": {
        "label": "指定周期",
        "placeholder": "限制指定周期可以使用优惠，留空则不限制",
        "description": "选择可以使用优惠券的订阅周期，留空表示不限制使用周期",
        "empty": "没有找到匹配的周期"
      },
      "limitPlan": {
        "label": "指定订阅",
        "placeholder": "限制指定订阅可以使用优惠，留空则不限制",
        "empty": "没有找到匹配的订阅"
      },
      "code": {
        "label": "自定义优惠码",
        "placeholder": "自定义优惠码，留空则自动生成",
        "description": "可以自定义优惠码，留空则系统自动生成"
      },
      "generateCount": {
        "label": "批量生成数量",
        "placeholder": "批量生成优惠码数量，留空则生成单个",
        "description": "批量生成多个优惠码，留空则只生成单个优惠码"
      },
      "submit": {
        "saving": "保存中...",
        "save": "保存"
      },
      "error": {
        "saveFailed": "保存优惠券失败"
      }
    },
    "period": {
      "monthly": "月",
      "quarterly": "季度",
      "half_yearly": "半年",
      "yearly": "年",
      "two_yearly": "两年",
      "three_yearly": "三年",
      "onetime": "一次性",
      "reset_traffic": "重置流量"
    }
  },
  "notice": {
    "title": "公告管理",
    "description": "在这里可以配置公告，包括添加、删除、编辑等操作。",
    "table": {
      "columns": {
        "id": "ID",
        "show": "显示状态",
        "title": "标题",
        "actions": "操作"
      },
      "toolbar": {
        "search": "搜索公告标题...",
        "reset": "重置",
        "sort": {
          "edit": "编辑排序",
          "save": "保存排序"
        }
      },
      "actions": {
        "edit": "编辑",
        "delete": {
          "title": "删除确认",
          "description": "确定要删除该条公告吗？此操作无法撤销。",
          "success": "删除成功"
        }
      }
    },
    "form": {
      "add": {
        "title": "添加公告",
        "button": "添加公告"
      },
      "edit": {
        "title": "编辑公告"
      },
      "fields": {
        "title": {
          "label": "标题",
          "placeholder": "请输入公告标题"
        },
        "content": {
          "label": "公告内容"
        },
        "img_url": {
          "label": "公告背景",
          "placeholder": "请输入公告背景图片URL"
        },
        "show": {
          "label": "显示"
        },
        "tags": {
          "label": "节点标签",
          "placeholder": "输入后回车添加标签"
        }
      },
      "buttons": {
        "cancel": "取消",
        "submit": "提交",
        "success": "提交成功"
      }
    }
  },
  "theme": {
    "title": "主题配置",
    "description": "主题配置，包括主题色、字体大小等。如果你采用前后分离的方式部署V2board，那么主题配置将不会生效。",
    "upload": {
      "button": "上传主题",
      "title": "上传主题",
      "description": "请上传一个有效的主题压缩包（.zip 格式）。主题包应包含完整的主题文件结构。",
      "dragText": "将主题文件拖放到此处，或者",
      "clickText": "点击选择",
      "supportText": "支持 .zip 格式的主题包",
      "uploading": "正在上传...",
      "error": {
        "format": "只支持上传 ZIP 格式的主题文件"
      }
    },
    "preview": {
      "title": "主题预览",
      "imageCount": "{{current}} / {{total}}"
    },
    "card": {
      "version": "版本: {{version}}",
      "currentTheme": "当前主题",
      "activateTheme": "激活主题",
      "configureTheme": "主题设置",
      "preview": "预览",
      "delete": {
        "title": "删除主题",
        "description": "确定要删除该主题吗？删除后无法恢复。",
        "button": "删除",
        "error": {
          "active": "不能删除当前使用的主题"
        }
      }
    },
    "config": {
      "title": "配置{{name}}主题",
      "description": "修改主题的样式、布局和其他显示选项。",
      "cancel": "取消",
      "save": "保存",
      "success": "保存成功"
    }
  },
  "ticket": {
    "title": "工单管理",
    "description": "在这里可以查看用户工单，包括查看、回复、关闭等操作。",
    "columns": {
      "id": "工单号",
      "subject": "主题",
      "level": "优先级",
      "status": "状态",
      "updated_at": "最后更新",
      "created_at": "创建时间",
      "actions": "操作"
    },
    "status": {
      "closed": "已关闭",
      "replied": "已回复",
      "pending": "待回复",
      "processing": "处理中"
    },
    "level": {
      "low": "低优先",
      "medium": "中优先",
      "high": "高优先"
    },
    "filter": {
      "placeholder": "搜索{field}...",
      "no_results": "未找到结果",
      "selected": "已选择 {count} 项",
      "clear": "清除筛选"
    },
    "actions": {
      "view_details": "查看详情",
      "close_ticket": "关闭工单",
      "close_confirm_title": "确认关闭工单",
      "close_confirm_description": "确定要关闭这个工单吗？关闭后将无法继续回复。",
      "close_confirm_button": "确认关闭",
      "close_success": "工单已关闭",
      "view_ticket": "查看工单"
    },
    "detail": {
      "no_messages": "暂无消息记录",
      "created_at": "创建于",
      "user_info": "用户信息",
      "traffic_records": "流量记录",
      "order_records": "订单记录",
      "input": {
        "closed_placeholder": "工单已关闭",
        "reply_placeholder": "输入回复内容...",
        "sending": "发送中...",
        "send": "发送"
      }
    },
    "list": {
      "title": "工单列表",
      "search_placeholder": "搜索工单标题或用户邮箱",
      "no_tickets": "暂无待处理工单",
      "no_search_results": "未找到匹配的工单"
    }
  },
  "server": {
    "manage": {
      "title": "节点管理",
      "description": "管理所有节点，包括添加、删除、编辑等操作。"
    },
    "columns": {
      "sort": "排序",
      "nodeId": "节点ID",
      "show": "显隐",
      "node": "节点",
      "address": "地址",
      "onlineUsers": {
        "title": "在线人数",
        "tooltip": "在线人数根据服务端上报频率而定"
      },
      "rate": {
        "title": "倍率",
        "tooltip": "流量扣费倍率"
      },
      "groups": {
        "title": "权限组",
        "tooltip": "可订阅到该节点的权限组",
        "empty": "--"
      },
      "type": "类型",
      "actions": "操作",
      "copyAddress": "复制连接地址",
      "internalPort": "内部端口",
      "status": {
        "0": "未运行",
        "1": "无人使用或异常",
        "2": "运行正常"
      },
      "actions_dropdown": {
        "edit": "编辑",
        "copy": "复制",
        "delete": {
          "title": "确认删除",
          "description": "此操作将永久删除该节点，删除后无法恢复。确定要继续吗？",
          "confirm": "删除"
        },
        "copy_success": "复制成功",
        "delete_success": "删除成功"
      }
    },
    "toolbar": {
      "search": "搜索节点...",
      "type": "类型",
      "reset": "重置",
      "sort": {
        "tip": "拖拽节点进行排序，完成后点击保存",
        "edit": "编辑排序",
        "save": "保存排序"
      }
    },
    "form": {
      "add_node": "添加节点",
      "edit_node": "编辑节点",
      "new_node": "新建节点",
      "name": {
        "label": "节点名称",
        "placeholder": "请输入节点名称",
        "error": "请输入有效的节点名称"
      },
      "rate": {
        "label": "倍率",
        "error": "请输入有效的倍率"
      },
      "code": {
        "label": "自定义节点ID",
        "optional": "(选填)",
        "placeholder": "请输入自定义节点ID"
      },
      "tags": {
        "label": "节点标签",
        "placeholder": "输入后回车添加标签"
      },
      "groups": {
        "label": "权限组",
        "add": "添加权限组",
        "placeholder": "请选择权限组",
        "empty": "未找到结果"
      },
      "host": {
        "label": "节点地址",
        "placeholder": "请输入节点域名或者IP"
      },
      "port": {
        "label": "连接端口",
        "placeholder": "用户连接端口",
        "tooltip": "用户实际连接使用的端口，这是客户端配置中需要填写的端口号。如果使用了中转或隧道，这个端口可能与服务器实际监听的端口不同。",
        "sync": "同步到服务端口"
      },
      "server_port": {
        "label": "服务端口",
        "placeholder": "服务端开放端口",
        "tooltip": "服务器实际监听的端口，这是在服务器上开放的真实端口。如果使用了中转或隧道，这个端口可能与用户连接端口不同。"
      },
      "parent": {
        "label": "父节点",
        "placeholder": "选择父节点",
        "none": "无"
      },
      "route": {
        "label": "路由组",
        "placeholder": "选择路由组",
        "empty": "未找到结果"
      },
      "submit": "提交",
      "cancel": "取消",
      "success": "提交成功"
    },
    "dynamic_form": {
      "shadowsocks": {
        "cipher": {
          "label": "加密算法",
          "placeholder": "选择加密算法"
        },
        "obfs": {
          "label": "混淆",
          "placeholder": "选择混淆方式",
          "none": "无",
          "http": "HTTP"
        },
        "obfs_settings": {
          "path": "路径",
          "host": "Host"
        }
      },
      "vmess": {
        "tls": {
          "label": "TLS",
          "placeholder": "请选择安全性",
          "disabled": "不支持",
          "enabled": "支持"
        },
        "tls_settings": {
          "server_name": {
            "label": "服务器名称指示(SNI)",
            "placeholder": "不使用请留空"
          },
          "allow_insecure": "允许不安全?"
        },
        "network": {
          "label": "传输协议",
          "placeholder": "选择传输协议"
        }
      },
      "trojan": {
        "server_name": {
          "label": "服务器名称指示(SNI)",
          "placeholder": "当节点地址于证书不一致时用于证书验证"
        },
        "allow_insecure": "允许不安全?",
        "network": {
          "label": "传输协议",
          "placeholder": "选择传输协议"
        }
      },
      "hysteria": {
        "version": {
          "label": "协议版本",
          "placeholder": "协议版本"
        },
        "alpn": {
          "label": "ALPN",
          "placeholder": "ALPN"
        },
        "obfs": {
          "label": "混淆",
          "type": {
            "label": "混淆实现",
            "placeholder": "选择混淆实现",
            "salamander": "Salamander"
          },
          "password": {
            "label": "混淆密码",
            "placeholder": "请输入混淆密码",
            "generate_success": "混淆密码生成成功"
          }
        },
        "tls": {
          "server_name": {
            "label": "服务器名称指示(SNI)",
            "placeholder": "当节点地址于证书不一致时用于证书验证"
          },
          "allow_insecure": "允许不安全?"
        },
        "bandwidth": {
          "up": {
            "label": "上行宽带",
            "placeholder": "请输入上行宽带",
            "suffix": "Mbps",
            "bbr_tip": "，留空则使用BBR"
          },
          "down": {
            "label": "下行宽带",
            "placeholder": "请输入下行宽带",
            "suffix": "Mbps",
            "bbr_tip": "，留空则使用BBR"
          }
        }
      },
      "vless": {
        "tls": {
          "label": "安全性",
          "placeholder": "请选择安全性",
          "none": "无",
          "tls": "TLS",
          "reality": "Reality"
        },
        "tls_settings": {
          "server_name": {
            "label": "服务器名称指示(SNI)",
            "placeholder": "不使用请留空"
          },
          "allow_insecure": "允许不安全?"
        },
        "reality_settings": {
          "server_name": {
            "label": "伪装站点(dest)",
            "placeholder": "例如：example.com"
          },
          "server_port": {
            "label": "端口(port)",
            "placeholder": "例如：443"
          },
          "allow_insecure": "允许不安全?",
          "private_key": {
            "label": "私钥(Private key)"
          },
          "public_key": {
            "label": "公钥(Public key)"
          },
          "short_id": {
            "label": "Short ID",
            "placeholder": "可留空，长度为2的倍数，最长16位",
            "description": "客户端可用的 shortId 列表，可用于区分不同的客户端，使用0-f的十六进制字符",
            "generate": "生成 Short ID",
            "success": "Short ID 生成成功"
          },
          "key_pair": {
            "generate": "生成密钥对",
            "success": "密钥对生成成功",
            "error": "生成密钥对失败"
          }
        },
        "network": {
          "label": "传输协议",
          "placeholder": "选择传输协议"
        },
        "flow": {
          "label": "流控",
          "placeholder": "选择流控"
        }
      }
    },
    "network_settings": {
      "edit_protocol": "编辑协议",
      "edit_protocol_config": "编辑协议配置",
      "use_template": "使用{{template}}模板",
      "json_config_placeholder": "请输入JSON配置",
      "json_config_placeholder_with_template": "请输入JSON配置或选择上方模板",
      "validation": {
        "must_be_object": "配置必须是一个JSON对象",
        "invalid_json": "无效的JSON格式"
      },
      "errors": {
        "save_failed": "保存时发生错误"
      }
    },
    "common": {
      "cancel": "取消",
      "confirm": "确定"
    }
  },
  "user": {
    "manage": {
      "title": "用户管理",
      "description": "在这里可以管理用户，包括增加、删除、编辑、查询等操作。"
    },
    "columns": {
      "is_admin": "管理员",
      "is_staff": "员工",
      "id": "ID",
      "email": "邮箱",
      "online_count": "在线设备",
      "status": "状态",
      "subscription": "订阅",
      "group": "权限组",
      "used_traffic": "已用流量",
      "total_traffic": "总流量",
      "expire_time": "到期时间",
      "balance": "余额",
      "commission": "佣金",
      "register_time": "注册时间",
      "actions": "操作",
      "device_limit": {
        "unlimited": "无设备数限制",
        "limited": "最多可同时在线 {{count}} 台设备"
      },
      "status_text": {
        "normal": "正常",
        "banned": "封禁"
      },
      "online_status": {
        "online": "当前在线",
        "never": "从未在线",
        "last_online": "最后在线时间: {{time}}",
        "offline_duration": {
          "days": "离线时长: {{count}}天",
          "hours": "离线时长: {{count}}小时",
          "minutes": "离线时长: {{count}}分钟",
          "seconds": "离线时长: {{count}}秒"
        }
      },
      "expire_status": {
        "permanent": "长期有效",
        "expired": "已过期 {{days}} 天",
        "remaining": "剩余 {{days}} 天"
      },
      "actions_menu": {
        "edit": "编辑",
        "assign_order": "分配订单",
        "copy_url": "复制订阅URL",
        "reset_secret": "重置UUID及订阅URL",
        "orders": "TA的订单",
        "invites": "TA的邀请",
        "traffic_records": "TA的流量记录",
        "delete": "删除",
        "delete_confirm_title": "确认删除用户",
        "delete_confirm_description": "此操作将永久删除用户 {{email}} 及其所有相关数据，包括订单、优惠码、流量记录、工单记录等信息。删除后无法恢复，是否继续？"
      }
    },
    "filter": {
      "selected": "已选择 {{count}} 项",
      "no_results": "未找到结果",
      "clear": "清除筛选",
      "search_placeholder": "搜索...",
      "email_search": "搜索用户邮箱...",
      "advanced": "高级筛选",
      "reset": "重置筛选",
      "sheet": {
        "title": "高级筛选",
        "description": "添加一个或多个筛选条件来精确查找用户",
        "conditions": "筛选条件",
        "add": "添加条件",
        "condition": "条件 {{number}}",
        "field": "选择字段",
        "operator": "选择操作符",
        "value": "输入值",
        "value_number": "输入数值({{unit}})",
        "reset": "重置",
        "apply": "应用筛选"
      },
      "fields": {
        "email": "邮箱",
        "id": "用户ID",
        "plan_id": "订阅",
        "transfer_enable": "流量",
        "total_used": "已用流量",
        "online_count": "在线设备",
        "expired_at": "到期时间",
        "uuid": "UUID",
        "token": "Token",
        "banned": "账号状态",
        "remark": "备注",
        "inviter_email": "邀请人邮箱",
        "invite_user_id": "邀请人ID",
        "is_admin": "管理员",
        "is_staff": "员工"
      },
      "operators": {
        "contains": "包含",
        "eq": "等于",
        "gt": "大于",
        "lt": "小于"
      },
      "status": {
        "normal": "正常",
        "banned": "禁用"
      },
      "boolean": {
        "true": "是",
        "false": "否"
      }
    },
    "generate": {
      "button": "创建用户",
      "title": "创建用户",
      "form": {
        "email": "邮箱",
        "email_prefix": "帐号(批量生成请留空)",
        "email_domain": "域",
        "password": "密码",
        "password_placeholder": "留空则密码与邮件相同",
        "expire_time": "到期时间",
        "expire_time_placeholder": "请选择用户到期日期，留空为长期有效",
        "permanent": "长期有效",
        "subscription": "订阅计划",
        "subscription_none": "无",
        "generate_count": "生成数量",
        "generate_count_placeholder": "如果为批量生产请输入生成数量",
        "cancel": "取消",
        "submit": "生成",
        "success": "生成成功"
      }
    },
    "edit": {
      "button": "编辑用户信息",
      "title": "用户管理",
      "form": {
        "email": "邮箱",
        "email_placeholder": "请输入邮箱",
        "inviter_email": "邀请人邮箱",
        "inviter_email_placeholder": "请输入邮箱",
        "password": "密码",
        "password_placeholder": "如需修改密码请输入",
        "balance": "余额",
        "balance_placeholder": "请输入余额",
        "commission_balance": "佣金余额",
        "commission_balance_placeholder": "请输入佣金余额",
        "upload": "已用上行",
        "upload_placeholder": "已用上行",
        "download": "已用下行",
        "download_placeholder": "已用下行",
        "total_traffic": "流量",
        "total_traffic_placeholder": "请输入流量",
        "expire_time": "到期时间",
        "expire_time_placeholder": "请选择用户到期日期，留空为长期有效",
        "expire_time_specific": "具体时间",
        "expire_time_today": "设为当天结束",
        "expire_time_permanent": "长期有效",
        "expire_time_1month": "一个月",
        "expire_time_3months": "三个月",
        "expire_time_confirm": "确定",
        "subscription": "订阅计划",
        "subscription_none": "无",
        "account_status": "账户状态",
        "commission_type": "佣金类型",
        "commission_type_system": "跟随系统设置",
        "commission_type_cycle": "循环返利",
        "commission_type_onetime": "首次返利",
        "commission_rate": "推荐返利比例",
        "commission_rate_placeholder": "为空则跟随站点设置返利比例",
        "discount": "专享折扣比例",
        "discount_placeholder": "为空则不享受专享折扣",
        "speed_limit": "限速",
        "speed_limit_placeholder": "留空则不限速",
        "device_limit": "设备限制",
        "device_limit_placeholder": "留空则不限制",
        "is_admin": "是否管理员",
        "is_staff": "是否员工",
        "remarks": "备注",
        "remarks_placeholder": "请在这里记录",
        "cancel": "取消",
        "submit": "提交",
        "success": "修改成功"
      }
    },
    "actions": {
      "title": "操作",
      "send_email": "发送邮件",
      "export_csv": "导出 CSV",
      "batch_ban": "批量封禁",
      "confirm_ban": {
        "title": "确认批量封禁",
        "filtered_description": "此操作将封禁所有符合当前筛选条件的用户。此操作无法撤销。",
        "all_description": "此操作将封禁系统中的所有用户。此操作无法撤销。",
        "cancel": "取消",
        "confirm": "确认封禁",
        "banning": "封禁中..."
      }
    },
    "messages": {
      "success": "成功",
      "error": "错误",
      "export": {
        "success": "导出成功",
        "failed": "导出失败"
      },
      "batch_ban": {
        "success": "批量封禁成功",
        "failed": "批量封禁失败"
      },
      "send_mail": {
        "success": "邮件发送成功",
        "failed": "邮件发送失败",
        "required_fields": "请填写所有必填字段"
      }
    },
    "send_mail": {
      "title": "发送邮件",
      "description": "向所选或已筛选的用户发送邮件",
      "subject": "主题",
      "content": "内容",
      "sending": "发送中...",
      "send": "发送"
    }
  },
  "subscribe": {
    "plan": {
      "title": "订阅套餐",
      "add": "添加套餐",
      "search": "搜索套餐...",
      "sort": {
        "edit": "编辑排序",
        "save": "保存排序"
      },
      "columns": {
        "id": "ID",
        "show": "显示",
        "sell": "新购",
        "renew": "续费",
        "renew_tooltip": "在订阅停止销售时，已购用户是否可以续费",
        "name": "名称",
        "stats": "统计",
        "group": "权限组",
        "price": "价格",
        "actions": "操作",
        "edit": "编辑",
        "delete": "删除",
        "delete_confirm": {
          "title": "确认删除",
          "description": "此操作将永久删除该订阅，删除后无法恢复。确定要继续吗？",
          "success": "删除成功"
        },
        "price_period": {
          "monthly": "月付",
          "quarterly": "季付",
          "half_yearly": "半年付",
          "yearly": "年付",
          "two_yearly": "两年付",
          "three_yearly": "三年付",
          "onetime": "流量包",
          "reset_traffic": "重置包",
          "unit": {
            "month": "元/月",
            "quarter": "元/季",
            "half_year": "元/半年",
            "year": "元/年",
            "two_year": "元/两年",
            "three_year": "元/三年",
            "times": "元/次"
          }
        }
      },
      "form": {
        "add_title": "添加套餐",
        "edit_title": "编辑套餐",
        "name": {
          "label": "套餐名称",
          "placeholder": "请输入套餐名称"
        },
        "group": {
          "label": "服务器分组",
          "add": "添加分组",
          "placeholder": "请选择服务器分组"
        },
        "transfer": {
          "label": "流量",
          "placeholder": "请输入流量限制",
          "unit": "GB"
        },
        "speed": {
          "label": "速度限制",
          "placeholder": "请输入速度限制",
          "unit": "Mbps"
        },
        "price": {
          "title": "价格设置",
          "base_price": "基础价格",
          "clear": {
            "button": "清空",
            "tooltip": "清空所有价格"
          },
          "period": {
            "monthly": "每月",
            "months": "{{count}}个月"
          },
          "onetime_desc": "一次性流量包，无时间限制",
          "reset_desc": "重置流量包，可多次使用"
        },
        "device": {
          "label": "设备限制",
          "placeholder": "请输入设备限制",
          "unit": "台"
        },
        "capacity": {
          "label": "容量限制",
          "placeholder": "请输入容量限制",
          "unit": "人"
        },
        "reset_method": {
          "label": "流量重置方式",
          "placeholder": "请选择重置方式",
          "description": "流量重置方式将决定如何重置流量",
          "options": {
            "follow_system": "跟随系统设置",
            "monthly_first": "每月首日",
            "monthly_reset": "每月购买日",
            "no_reset": "不重置",
            "yearly_first": "每年首日",
            "yearly_reset": "每年购买日"
          }
        },
        "content": {
          "label": "套餐说明",
          "placeholder": "请输入套餐说明",
          "description": "支持 Markdown 格式",
          "preview": "预览",
          "preview_button": {
            "show": "显示预览",
            "hide": "隐藏预览"
          },
          "template": {
            "button": "使用模板",
            "tooltip": "使用默认模板",
            "content": "## 套餐详情\n\n- 流量：{{transfer}} GB\n- 速度限制：{{speed}} Mbps\n- 同时在线设备：{{devices}} 台\n\n## 服务说明\n\n1. 流量{{reset_method}}重置\n2. 支持多平台使用\n3. 7×24小时技术支持"
          }
        },
        "force_update": {
          "label": "强制更新用户套餐"
        },
        "submit": {
          "cancel": "取消",
          "submit": "提交",
          "submitting": "提交中...",
          "success": {
            "add": "套餐添加成功",
            "update": "套餐更新成功"
          }
        }
      },
      "page": {
        "description": "在这里可以配置订阅计划，包括添加、删除、编辑等操作。"
      }
    }
  },
  "auth": {
    "signIn": {
      "title": "登录",
      "description": "请输入您的邮箱和密码登录系统",
      "email": "邮箱地址",
      "emailPlaceholder": "name@example.com",
      "password": "密码",
      "passwordPlaceholder": "请输入密码",
      "forgotPassword": "忘记密码？",
      "submit": "登录",
      "rememberMe": "记住我",
      "resetPassword": {
        "title": "重置密码",
        "description": "在站点目录下执行以下命令找回密码",
        "command": "php artisan reset:password 管理员邮箱"
      },
      "validation": {
        "emailRequired": "请输入邮箱地址",
        "emailInvalid": "邮箱地址格式不正确",
        "passwordRequired": "请输入密码",
        "passwordLength": "密码长度至少为7个字符"
      }
    }
  },
  "sidebar": {
    "dashboard": "仪表盘",
    "systemManagement": "系统管理",
    "systemConfig": "系统配置",
    "pluginManagement": "插件管理",
    "themeConfig": "主题配置",
    "noticeManagement": "公告管理",
    "paymentConfig": "支付配置",
    "knowledgeManagement": "知识库管理",
    "nodeManagement": "节点管理",
    "permissionGroupManagement": "权限组管理",
    "routeManagement": "路由管理",
    "subscriptionManagement": "订阅管理",
    "planManagement": "套餐管理",
    "orderManagement": "订单管理",
    "couponManagement": "优惠券管理",
    "userManagement": "用户管理",
    "ticketManagement": "工单管理"
  }
};