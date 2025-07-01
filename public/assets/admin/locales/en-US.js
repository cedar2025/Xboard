window.XBOARD_TRANSLATIONS = window.XBOARD_TRANSLATIONS || {};
window.XBOARD_TRANSLATIONS['en-US'] = {
  "payment": {
    "title": "Payment Configuration",
    "description": "Configure payment methods including Alipay, WeChat Pay, etc.",
    "table": {
      "columns": {
        "id": "ID",
        "enable": "Enable",
        "name": "Display Name",
        "payment": "Payment Gateway",
        "notify_url": "Notify URL",
        "notify_url_tooltip": "The payment gateway will send notifications to this address. Please ensure it's accessible through your firewall.",
        "actions": "Actions"
      },
      "actions": {
        "edit": "Edit",
        "delete": {
          "title": "Confirm Delete",
          "description": "Are you sure you want to delete this payment method? This action cannot be undone.",
          "success": "Successfully deleted"
        }
      },
      "toolbar": {
        "search": "Search payment methods...",
        "reset": "Reset",
        "sort": {
          "hint": "Drag payment methods to sort, click save when finished",
          "save": "Save Order",
          "edit": "Edit Order"
        }
      }
    },
    "form": {
      "add": {
        "button": "Add Payment Method",
        "title": "Add Payment Method"
      },
      "edit": {
        "title": "Edit Payment Method"
      },
      "fields": {
        "name": {
          "label": "Display Name",
          "placeholder": "Enter payment name",
          "description": "Used for frontend display"
        },
        "icon": {
          "label": "Icon URL",
          "placeholder": "https://example.com/icon.svg",
          "description": "Icon URL for frontend display"
        },
        "notify_domain": {
          "label": "Notify Domain",
          "placeholder": "https://example.com",
          "description": "Domain for gateway notifications"
        },
        "handling_fee_percent": {
          "label": "Percentage Fee (%)",
          "placeholder": "0-100"
        },
        "handling_fee_fixed": {
          "label": "Fixed Fee",
          "placeholder": "0"
        },
        "payment": {
          "label": "Payment Gateway",
          "placeholder": "Select payment gateway",
          "description": "Choose the payment gateway to use"
        }
      },
      "validation": {
        "name": {
          "min": "Name must be at least 2 characters",
          "max": "Name cannot exceed 30 characters"
        },
        "notify_domain": {
          "url": "Please enter a valid URL"
        },
        "payment": {
          "required": "Please select a payment gateway"
        }
      },
      "buttons": {
        "cancel": "Cancel",
        "submit": "Submit"
      },
      "messages": {
        "success": "Saved successfully"
      }
    }
  },
  "knowledge": {
    "title": "Knowledge Base",
    "description": "Here you can configure the knowledge base, including adding, deleting, and editing operations.",
    "columns": {
      "id": "ID",
      "status": "Status",
      "title": "Title",
      "category": "Category",
      "actions": "Actions"
    },
    "form": {
      "add": "Add Knowledge",
      "edit": "Edit Knowledge",
      "title": "Title",
      "titlePlaceholder": "Please enter knowledge title",
      "category": "Category",
      "categoryPlaceholder": "Please enter category, it will be automatically classified",
      "language": "Language",
      "languagePlaceholder": "Please select language",
      "content": "Content",
      "show": "Show",
      "cancel": "Cancel",
      "submit": "Submit"
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
      "deleteConfirm": "Confirm Delete",
      "deleteDescription": "This action will permanently delete this knowledge base record and cannot be recovered. Are you sure you want to continue?",
      "deleteButton": "Delete",
      "operationSuccess": "Operation Successful"
    },
    "toolbar": {
      "searchPlaceholder": "Search knowledge...",
      "reset": "Reset",
      "sortModeHint": "Drag knowledge items to sort, click save when done",
      "editSort": "Edit Sort",
      "saveSort": "Save Sort"
    }
  },
  "search": {
    "placeholder": "Search menus and functions...",
    "title": "Menu Navigation",
    "noResults": "No results found",
    "shortcut": {
      "label": "Search",
      "key": "⌘K"
    }
  },
  "nav": {
    "dashboard": "Dashboard",
    "systemManagement": "System Management",
    "systemConfig": "System Configuration",
    "themeConfig": "Theme Configuration",
    "noticeManagement": "Notice Management",
    "pluginManagement": "Plugin Management",
    "paymentConfig": "Payment Configuration",
    "knowledgeManagement": "Knowledge Management",
    "nodeManagement": "Node Management",
    "permissionGroupManagement": "Permission Group",
    "routeManagement": "Route Management",
    "subscriptionManagement": "Subscription",
    "planManagement": "Plan Management",
    "orderManagement": "Order Management",
    "couponManagement": "Coupon Management",
    "userManagement": "User Management",
    "trafficResetLogs": "Traffic Reset Logs",
    "ticketManagement": "Ticket Management"
  },
  "plugin": {
    "title": "Plugin Management",
    "description": "Manage and configure system plugins",
    "search": {
      "placeholder": "Search plugin name or description..."
    },
    "category": {
      "placeholder": "Select Category",
      "all": "All",
      "other": "Other"
    },
    "tabs": {
      "all": "All Plugins",
      "installed": "Installed",
      "available": "Available"
    },
    "status": {
      "enabled": "Enabled",
      "disabled": "Disabled"
    },
    "button": {
      "install": "Install",
      "config": "Configure",
      "enable": "Enable",
      "disable": "Disable",
      "uninstall": "Uninstall"
    },
    "upload": {
      "button": "Upload Plugin",
      "title": "Upload Plugin",
      "description": "Upload a plugin package (.zip)",
      "dragText": "Drag and drop plugin package here, or",
      "clickText": "browse",
      "supportText": "Supports .zip files only",
      "uploading": "Uploading...",
      "error": {
        "format": "Only .zip files are supported"
      }
    },
    "delete": {
      "title": "Delete Plugin",
      "description": "Are you sure you want to delete this plugin? This action cannot be undone.",
      "button": "Delete"
    },
    "uninstall": {
      "title": "Uninstall Plugin",
      "description": "Are you sure you want to uninstall this plugin? Plugin data will be cleared after uninstallation.",
      "button": "Uninstall"
    },
    "config": {
      "title": "Configuration",
      "description": "Modify plugin configuration",
      "save": "Save",
      "cancel": "Cancel"
    },
    "author": "Author",
    "messages": {
      "installSuccess": "Plugin installed successfully",
      "installError": "Failed to install plugin",
      "uninstallSuccess": "Plugin uninstalled successfully",
      "uninstallError": "Failed to uninstall plugin",
      "enableSuccess": "Plugin enabled successfully",
      "enableError": "Failed to enable plugin",
      "disableSuccess": "Plugin disabled successfully",
      "disableError": "Failed to disable plugin",
      "configLoadError": "Failed to load plugin configuration",
      "configSaveSuccess": "Configuration saved successfully",
      "configSaveError": "Failed to save configuration",
      "uploadSuccess": "Plugin uploaded successfully",
      "uploadError": "Failed to upload plugin",
      "deleteSuccess": "Plugin deleted successfully",
      "deleteError": "Failed to delete plugin"
    }
  },
  "settings": {
    "title": "System Settings",
    "description": "Manage core system configurations, including site, security, subscription, invite commission, nodes, email, and notifications",
    "server": {
      "title": "Server Configuration",
      "description": "Configure node communication and synchronization settings, including communication keys, polling intervals, load balancing and other advanced options.",
      "server_token": {
        "title": "Communication Token",
        "placeholder": "Enter communication token",
        "description": "Used for authentication between servers",
        "generate_tooltip": "Click to generate random token"
      },
      "server_pull_interval": {
        "title": "Node Pull Action Polling Interval",
        "description": "The frequency at which nodes retrieve data from the panel.",
        "placeholder": "Enter pull interval"
      },
      "server_push_interval": {
        "title": "Node Push Action Polling Interval",
        "description": "The frequency at which nodes push data to the panel.",
        "placeholder": "Enter push interval"
      },
      "device_limit_mode": {
        "title": "Device Limit Mode",
        "description": "In relaxed mode, using multiple nodes from the same IP address counts as one device.",
        "strict": "Strict Mode",
        "relaxed": "Relaxed Mode",
        "placeholder": "Select device limit mode"
      }
    },
    "invite": {
      "title": "Invitation & Commission Settings",
      "description": "Configure invitation registration and commission related settings.",
      "invite_force": {
        "title": "Enable Forced Invitation",
        "description": "When enabled, only invited users can register."
      },
      "invite_commission": {
        "title": "Invitation Commission Percentage",
        "description": "Default global commission distribution ratio, you can configure individual ratios in user management.",
        "placeholder": "Enter commission percentage"
      },
      "invite_gen_limit": {
        "title": "Invitation Code Generation Limit",
        "description": "Maximum number of invitation codes a user can create",
        "placeholder": "Enter generation limit"
      },
      "invite_never_expire": {
        "title": "Never Expire Invitation Code",
        "description": "When enabled, invitation codes will not expire after use, otherwise they will expire after being used."
      },
      "commission_first_time": {
        "title": "First-time Commission Only",
        "description": "When enabled, commission will only be generated on the first payment of the invitee, can be configured individually in user management."
      },
      "commission_auto_check": {
        "title": "Automatic Commission Confirmation",
        "description": "When enabled, commission will be automatically confirmed 3 days after order completion."
      },
      "commission_withdraw_limit": {
        "title": "Withdrawal Threshold (Yuan)",
        "description": "Withdrawal requests below this threshold will not be submitted.",
        "placeholder": "Enter withdrawal threshold"
      },
      "commission_withdraw_method": {
        "title": "Withdrawal Methods",
        "description": "Supported withdrawal methods, separate multiple methods with commas.",
        "placeholder": "Enter withdrawal methods, separate with commas"
      },
      "withdraw_close": {
        "title": "Disable Withdrawals",
        "description": "When enabled, users will be prohibited from requesting withdrawals, and invitation commissions will go directly to user balance."
      },
      "commission_distribution": {
        "title": "Three-level Distribution",
        "description": "When enabled, commission will be split according to the three set ratios, total should not exceed 100%.",
        "l1": "Level 1 Inviter Ratio",
        "l2": "Level 2 Inviter Ratio",
        "l3": "Level 3 Inviter Ratio",
        "placeholder": "Enter ratio e.g. 50"
      },
      "saving": "Saving..."
    },
    "site": {
      "title": "Site Settings",
      "description": "Configure basic site information, including site name, description, currency unit, and other core settings.",
      "form": {
        "siteName": {
          "label": "Site Name",
          "placeholder": "Please enter site name",
          "description": "Used where site name needs to be displayed."
        },
        "siteDescription": {
          "label": "Site Description",
          "placeholder": "Please enter site description",
          "description": "Used where site description needs to be displayed."
        },
        "siteUrl": {
          "label": "Site URL",
          "placeholder": "Please enter site URL, without trailing /",
          "description": "Current website URL, will be used in emails and other places where URL is needed."
        },
        "forceHttps": {
          "label": "Force HTTPS",
          "description": "Need to enable when the site is not using HTTPS but CDN or reverse proxy has forced HTTPS."
        },
        "logo": {
          "label": "LOGO",
          "placeholder": "Please enter LOGO URL, without trailing /",
          "description": "Used where LOGO needs to be displayed."
        },
        "subscribeUrl": {
          "label": "Subscribe URL",
          "placeholder": "Used for subscription, multiple URLs separated by ','. Leave empty to use site URL.",
          "description": "Used for subscription, leave empty to use site URL."
        },
        "tosUrl": {
          "label": "Terms of Service (TOS) URL",
          "placeholder": "Please enter TOS URL, without trailing /",
          "description": "Used to link to Terms of Service (TOS)"
        },
        "stopRegister": {
          "label": "Stop New User Registration",
          "description": "When enabled, no one will be able to register."
        },
        "tryOut": {
          "label": "Registration Trial",
          "placeholder": "Disabled",
          "description": "Select the subscription for trial, if no options please add in subscription management first.",
          "duration": {
            "label": "Trial Duration",
            "placeholder": "0",
            "description": "Trial duration in hours."
          }
        },
        "currency": {
          "label": "Currency Unit",
          "placeholder": "CNY",
          "description": "For display only, changing this will affect all currency units in the system."
        },
        "currencySymbol": {
          "label": "Currency Symbol",
          "placeholder": "¥",
          "description": "For display only, changing this will affect all currency symbols in the system."
        }
      }
    },
    "safe": {
      "title": "Security Settings",
      "description": "Configure system security options, including login verification, password policies, and API access settings.",
      "form": {
        "emailVerify": {
          "label": "Email Verification",
          "description": "When enabled, users will be required to verify their email."
        },
        "gmailLimit": {
          "label": "Disable Gmail Aliases",
          "description": "When enabled, Gmail aliases will not be allowed to register."
        },
        "safeMode": {
          "label": "Safe Mode",
          "description": "When enabled, accessing the site through domains other than the site URL will be blocked with 403."
        },
        "securePath": {
          "label": "Admin Path",
          "placeholder": "admin",
          "description": "Admin management path, changing this will modify the original admin path"
        },
        "emailWhitelist": {
          "label": "Email Suffix Whitelist",
          "description": "When enabled, only email suffixes in the list will be allowed to register.",
          "suffixes": {
            "label": "Email Suffixes",
            "placeholder": "Enter email suffixes, one per line",
            "description": "Enter the allowed email suffixes, one per line"
          }
        },
        "captcha": {
          "enable": {
            "label": "Enable Captcha",
            "description": "When enabled, users will need to pass captcha verification when registering."
          },
          "type": {
            "label": "Captcha Type",
            "description": "Select the captcha service type to use",
            "options": {
              "recaptcha": "Google reCAPTCHA v2",
              "recaptcha-v3": "Google reCAPTCHA v3",
              "turnstile": "Cloudflare Turnstile"
            }
          },
          "recaptcha": {
            "key": {
              "label": "reCAPTCHA Key",
              "placeholder": "Enter reCAPTCHA key",
              "description": "Enter your reCAPTCHA key"
            },
            "siteKey": {
              "label": "reCAPTCHA Site Key",
              "placeholder": "Enter reCAPTCHA site key",
              "description": "Enter your reCAPTCHA site key"
            }
          },
          "recaptcha_v3": {
            "secretKey": {
              "label": "reCAPTCHA v3 Key",
              "placeholder": "Enter reCAPTCHA v3 key",
              "description": "Enter your reCAPTCHA v3 server key"
            },
            "siteKey": {
              "label": "reCAPTCHA v3 Site Key",
              "placeholder": "Enter reCAPTCHA v3 site key",
              "description": "Enter your reCAPTCHA v3 site key"
            },
            "scoreThreshold": {
              "label": "Score Threshold",
              "placeholder": "0.5",
              "description": "Set verification score threshold (0-1), higher scores indicate more likely human behavior"
            }
          },
          "turnstile": {
            "secretKey": {
              "label": "Turnstile Key",
              "placeholder": "Enter Turnstile key",
              "description": "Enter your Cloudflare Turnstile key"
            },
            "siteKey": {
              "label": "Turnstile Site Key",
              "placeholder": "Enter Turnstile site key",
              "description": "Enter your Cloudflare Turnstile site key"
            }
          }
        },
        "registerLimit": {
          "enable": {
            "label": "IP Registration Limit",
            "description": "When enabled, the number of registrations from the same IP will be limited."
          },
          "count": {
            "label": "Registration Count",
            "placeholder": "Enter maximum registration count",
            "description": "Maximum number of registrations allowed from the same IP"
          },
          "expire": {
            "label": "Limit Duration",
            "placeholder": "Enter limit duration in minutes",
            "description": "Duration of the registration limit in minutes"
          }
        },
        "passwordLimit": {
          "enable": {
            "label": "Password Attempt Limit",
            "description": "When enabled, the number of password attempts will be limited."
          },
          "count": {
            "label": "Attempt Count",
            "placeholder": "Enter maximum attempt count",
            "description": "Maximum number of password attempts allowed"
          },
          "expire": {
            "label": "Lock Duration",
            "placeholder": "Enter lock duration in minutes",
            "description": "Duration of the account lock in minutes"
          }
        }
      }
    },
    "subscribe": {
      "title": "Subscription Settings",
      "description": "Manage subscription-related configurations, including subscription link format, update frequency, traffic statistics, and other settings.",
      "plan_change_enable": {
        "title": "Allow Subscription Changes",
        "description": "When enabled, users will be able to change their subscription plans."
      },
      "reset_traffic_method": {
        "title": "Monthly Traffic Reset Method",
        "description": "Global traffic reset method, defaults to the 1st of each month. Can be set individually for subscriptions in subscription management.",
        "options": {
          "monthly_first": "1st of Each Month",
          "monthly_reset": "Monthly Reset",
          "no_reset": "No Reset",
          "yearly_first": "January 1st",
          "yearly_reset": "Yearly Reset"
        }
      },
      "surplus_enable": {
        "title": "Enable Deduction Plan",
        "description": "When enabled, the system will deduct from the original subscription when users change subscriptions, refer to documentation for details."
      },
      "new_order_event": {
        "title": "Trigger Event on New Subscription",
        "description": "This task will be triggered when a new subscription is completed.",
        "options": {
          "no_action": "No Action",
          "reset_traffic": "Reset User Traffic"
        }
      },
      "renew_order_event": {
        "title": "Trigger Event on Subscription Renewal",
        "description": "This task will be triggered when a subscription renewal is completed.",
        "options": {
          "no_action": "No Action",
          "reset_traffic": "Reset User Traffic"
        }
      },
      "change_order_event": {
        "title": "Trigger Event on Subscription Change",
        "description": "This task will be triggered when a subscription change is completed.",
        "options": {
          "no_action": "No Action",
          "reset_traffic": "Reset User Traffic"
        }
      },
      "subscribe_path": {
        "title": "Subscription Path",
        "description": "Subscription path, modifying will change the original subscribe path",
        "current_format": "Current subscription path format: {path}/xxxxxxxxxx"
      },
      "show_info_to_server": {
        "title": "Show Subscription Info in Nodes",
        "description": "When enabled, subscription information will be output when users subscribe to nodes."
      },
      "show_protocol_to_server": {
        "title": "Show Protocol in Node Names",
        "description": "When enabled, subscription lines will include protocol names (e.g., [Hy2]Hong Kong)"
      },
      "saving": "Saving...",
      "plan": {
        "title": "Subscription Plans",
        "add": "Add Plan",
        "search": "Search plans...",
        "sort": {
          "edit": "Edit Sort",
          "save": "Save Sort"
        },
        "columns": {
          "id": "ID",
          "show": "Show",
          "sell": "Sell",
          "renew": "Renew",
          "renew_tooltip": "Whether existing users can renew when the subscription stops selling",
          "name": "Name",
          "stats": "Statistics",
          "group": "Permission Group",
          "price": "Price",
          "actions": "Actions",
          "edit": "Edit",
          "delete": "Delete",
          "delete_confirm": {
            "title": "Confirm Delete",
            "description": "This action will permanently delete this subscription and cannot be undone. Are you sure you want to continue?",
            "success": "Successfully deleted"
          },
          "price_period": {
            "monthly": "Monthly",
            "quarterly": "Quarterly",
            "half_yearly": "Half Yearly",
            "yearly": "Yearly",
            "two_yearly": "Two Years",
            "three_yearly": "Three Years",
            "onetime": "One Time",
            "reset_traffic": "Reset Traffic",
            "unit": {
              "month": "/month",
              "quarter": "/quarter",
              "half_year": "/half year",
              "year": "/year",
              "two_year": "/2 years",
              "three_year": "/3 years",
              "times": "/time"
            }
          }
        },
        "form": {
          "add_title": "Add Plan",
          "edit_title": "Edit Plan",
          "name": {
            "label": "Plan Name",
            "placeholder": "Enter plan name"
          },
          "group": {
            "label": "Permission Group",
            "placeholder": "Select permission group",
            "add": "Add Group"
          },
          "transfer": {
            "label": "Traffic",
            "placeholder": "Enter traffic size",
            "unit": "GB"
          },
          "speed": {
            "label": "Speed Limit",
            "placeholder": "Enter speed limit",
            "unit": "Mbps"
          },
          "price": {
            "title": "Price Settings",
            "base_price": "Base monthly price",
            "clear": {
              "button": "Clear Prices",
              "tooltip": "Clear all period price settings"
            }
          },
          "device": {
            "label": "Device Limit",
            "placeholder": "Leave empty for no limit",
            "unit": "devices"
          },
          "capacity": {
            "label": "Capacity Limit",
            "placeholder": "Leave empty for no limit",
            "unit": "users"
          },
          "reset_method": {
            "label": "Traffic Reset Method",
            "placeholder": "Select traffic reset method",
            "description": "Set how subscription traffic is reset, different methods affect how user traffic is calculated",
            "options": {
              "follow_system": "Follow System Settings",
              "monthly_first": "1st of Each Month",
              "monthly_reset": "Monthly Reset",
              "no_reset": "No Reset",
              "yearly_first": "January 1st",
              "yearly_reset": "Yearly Reset"
            }
          },
          "content": {
            "label": "Plan Description",
            "placeholder": "Write plan description here...",
            "description": "Supports Markdown format, you can use headings, lists, bold, italic and other styles to beautify the content",
            "preview": "Preview",
            "preview_button": {
              "show": "Show Preview",
              "hide": "Hide Preview"
            },
            "template": {
              "button": "Use Template",
              "tooltip": "Click to use preset plan description template",
              "content": "## Plan Features\n• High-speed and stable global network access\n• Support multiple devices online simultaneously\n• Unlimited traffic reset\n\n## Usage Instructions\n1. Supported devices: iOS, Android, Windows, macOS\n2. 24/7 technical support\n3. Automatic periodic traffic reset\n\n## Notes\n- No abuse allowed\n- Comply with local laws and regulations\n- Support plan changes anytime"
            }
          },
          "force_update": {
            "label": "Force Update to Users"
          },
          "submit": {
            "submitting": "Submitting...",
            "submit": "Submit",
            "cancel": "Cancel",
            "success": {
              "add": "Plan added successfully",
              "update": "Plan updated successfully"
            }
          }
        },
        "page": {
          "description": "Here you can configure subscription plans, including adding, deleting, and editing operations."
        }
      }
    },
    "email": {
      "title": "Email Settings",
      "description": "Configure system email service for sending verification codes, password resets, and notifications, supporting various SMTP providers.",
      "email_host": {
        "title": "SMTP Host",
        "description": "SMTP server address, e.g., smtp.gmail.com"
      },
      "email_port": {
        "title": "SMTP Port",
        "description": "SMTP server port, common ports: 25, 465, 587"
      },
      "email_username": {
        "title": "SMTP Username",
        "description": "SMTP authentication username"
      },
      "email_password": {
        "title": "SMTP Password",
        "description": "SMTP authentication password or application-specific password"
      },
      "email_encryption": {
        "title": "Encryption Method",
        "description": "Email encryption method",
        "none": "None",
        "ssl": "SSL/TLS",
        "tls": "STARTTLS"
      },
      "email_from": {
        "title": "From Address",
        "description": "Sender's email address"
      },
      "email_from_name": {
        "title": "From Name",
        "description": "Sender's display name"
      },
      "email_template": {
        "title": "Email Template",
        "description": "You can check the documentation for how to customize email templates",
        "placeholder": "Select email template"
      },
      "remind_mail": {
        "title": "Email Reminders",
        "description": "When enabled, users will receive email notifications when their subscription is about to expire or when traffic is running low."
      },
      "test": {
        "title": "Send Test Email",
        "sending": "Sending...",
        "description": "Send a test email to verify the configuration",
        "success": "Test email sent successfully",
        "error": "Failed to send test email"
      }
    },
    "telegram": {
      "title": "Telegram Settings",
      "description": "Configure Telegram bot functionality for user notifications, account binding, and command interactions.",
      "bot_token": {
        "title": "Bot Token",
        "description": "Please enter the token provided by Botfather.",
        "placeholder": "0000000000:xxxxxxxxx_xxxxxxxxxxxxxxx"
      },
      "webhook": {
        "title": "Set Webhook",
        "description": "Set up webhook for the bot. Without setting it, you won't receive Telegram notifications.",
        "button": "One-Click Setup",
        "setting": "Setting Webhook...",
        "success": "Webhook set successfully"
      },
      "bot_enable": {
        "title": "Enable Bot Notifications",
        "description": "When enabled, the bot will send basic notifications to administrators and users who have bound their Telegram accounts."
      },
      "discuss_link": {
        "title": "Group Link",
        "description": "Once filled in, it will be displayed on the user side or used where needed.",
        "placeholder": "https://t.me/xxxxxx"
      }
    },
    "app": {
      "title": "APP Settings",
      "description": "Manage mobile application configurations, including API interfaces, version control, and push notifications.",
      "common": {
        "placeholder": "Please input"
      },
      "windows": {
        "version": {
          "title": "Windows Version",
          "description": "Current version number of Windows client"
        },
        "download": {
          "title": "Windows Download URL",
          "description": "Download link for Windows client"
        }
      },
      "macos": {
        "version": {
          "title": "macOS Version",
          "description": "Current version number of macOS client"
        },
        "download": {
          "title": "macOS Download URL",
          "description": "Download link for macOS client"
        }
      },
      "android": {
        "version": {
          "title": "Android Version",
          "description": "Current version number of Android client"
        },
        "download": {
          "title": "Android Download URL",
          "description": "Download link for Android client"
        }
      }
    },
    "common": {
      "saving": "Saving...",
      "save_success": "Saved automatically",
      "placeholder": "Please input",
      "autoSaved": "Saved automatically"
    },
    "subscribe_template": {
      "title": "Subscribe Templates",
      "description": "Configure subscription templates for different clients",
      "singbox": {
        "title": "Sing-box Template",
        "description": "Configure subscription template format for Sing-box"
      },
      "clash": {
        "title": "Clash Template",
        "description": "Configure subscription template format for Clash"
      },
      "clashmeta": {
        "title": "Clash Meta Template",
        "description": "Configure subscription template format for Clash Meta"
      },
      "stash": {
        "title": "Stash Template",
        "description": "Configure subscription template format for Stash"
      },
      "surge": {
        "title": "Surge Template",
        "description": "Configure subscription template format for Surge"
      },
      "surfboard": {
        "title": "Surfboard Template",
        "description": "Configure subscription template format for Surfboard"
      }
    }
  },
  "group": {
    "title": "Permission Groups",
    "description": "Manage all permission groups, including adding, deleting, and editing operations.",
    "columns": {
      "id": "Group ID",
      "name": "Group Name",
      "usersCount": "Users Count",
      "serverCount": "Nodes Count",
      "actions": "Actions"
    },
    "form": {
      "add": "Add Group",
      "edit": "Edit Group",
      "create": "Create Group",
      "update": "Update",
      "name": "Group Name",
      "namePlaceholder": "Please enter group name",
      "nameDescription": "Group name is used to identify different user groups, it's recommended to use meaningful names.",
      "cancel": "Cancel",
      "editDescription": "Modify group information, changes will take effect immediately.",
      "createDescription": "Create a new permission group to assign different permissions to different users."
    },
    "toolbar": {
      "searchPlaceholder": "Search groups...",
      "reset": "Reset"
    },
    "messages": {
      "deleteConfirm": "Confirm Delete",
      "deleteDescription": "This action will permanently delete this permission group and cannot be recovered. Are you sure you want to continue?",
      "deleteButton": "Delete",
      "createSuccess": "Created Successfully",
      "updateSuccess": "Updated Successfully",
      "nameValidation": {
        "min": "Group name must be at least 2 characters",
        "max": "Group name cannot exceed 50 characters",
        "pattern": "Group name can only contain letters, numbers, Chinese characters, underscores and hyphens"
      }
    }
  },
  "traffic": {
    "trafficRecord": {
      "title": "Traffic Usage Records",
      "time": "Time",
      "upload": "Upload",
      "download": "Download",
      "rate": "Rate",
      "total": "Total",
      "noRecords": "No records found",
      "perPage": "Show per page",
      "records": "records",
      "page": "Page {{current}} / {{total}}",
      "multiplier": "{{value}}x"
    }
  },
  "common": {
    "loading": "Loading...",
    "error": "Error",
    "success": "Success",
    "save": "Save",
    "cancel": "Cancel",
    "confirm": "Confirm",
    "close": "Close",
    "delete": {
      "success": "Deleted successfully",
      "failed": "Failed to delete"
    },
    "edit": "Edit",
    "view": "View",
    "toggleNavigation": "Toggle Navigation",
    "toggleSidebar": "Toggle Sidebar",
    "search": "Search...",
    "theme": {
      "light": "Light",
      "dark": "Dark",
      "system": "System"
    },
    "user": "User",
    "defaultEmail": "user@example.com",
    "settings": "Settings",
    "logout": "Logout",
    "copy": {
      "success": "Copied successfully",
      "failed": "Failed to copy",
      "error": "Copy failed",
      "errorLog": "Error copying to clipboard"
    },
    "table": {
      "noData": "No data available",
      "pagination": {
        "selected": "{{selected}} of {{total}} items selected",
        "itemsPerPage": "Per page",
        "page": "Page",
        "pageOf": "of {{total}} pages",
        "firstPage": "Go to first page",
        "previousPage": "Previous page",
        "nextPage": "Next page",
        "lastPage": "Go to last page"
      }
    },
    "update": {
      "title": "System Update",
      "newVersion": "New Version Available",
      "currentVersion": "Current Version",
      "latestVersion": "Latest Version",
      "updateLater": "Update Later",
      "updateNow": "Update Now",
      "updating": "Updating...",
      "updateSuccess": "Update successful, system will restart shortly",
      "updateFailed": "Update failed, please try again later"
    }
  },
  "dashboard": {
    "title": "Dashboard",
    "stats": {
      "newUsers": "New Users",
      "totalScore": "Total Score",
      "monthlyUpload": "Monthly Upload",
      "vsLastMonth": "vs Last Month",
      "vsYesterday": "vs Yesterday",
      "todayIncome": "Today's Income",
      "monthlyIncome": "Monthly Income",
      "totalIncome": "Total Income",
      "totalUsers": "Total Users",
      "activeUsers": "Active Users: {{count}}",
      "totalOrders": "Total Orders",
      "revenue": "Revenue",
      "todayRegistered": "Today Registered",
      "monthlyRegistered": "Monthly Registered",
      "onlineUsers": "Online Users",
      "pendingTickets": "Pending Tickets",
      "hasPendingTickets": "There are tickets that need attention",
      "noPendingTickets": "No pending tickets",
      "pendingCommission": "Pending Commission",
      "hasPendingCommission": "There are commissions that need confirmation",
      "noPendingCommission": "No pending commission",
      "monthlyNewUsers": "Monthly New Users",
      "monthlyDownload": "Monthly Download",
      "todayTraffic": "Today: {{value}}",
      "activeUserTrend": "Active User Trend",
      "realtimeUsers": "Realtime Users",
      "todayPeak": "Today's Peak",
      "vsLastWeek": "vs Last Week"
    },
    "trafficRank": {
      "nodeTrafficRank": "Node Traffic Rank",
      "userTrafficRank": "User Traffic Rank",
      "today": "Today",
      "last7days": "Last 7 Days",
      "last30days": "Last 30 Days",
      "customRange": "Custom Range",
      "selectTimeRange": "Select Time Range",
      "selectDateRange": "Select Date Range",
      "currentTraffic": "Current Traffic",
      "previousTraffic": "Previous Traffic",
      "changeRate": "Change Rate",
      "recordTime": "Record Time"
    },
    "overview": {
      "title": "Revenue Overview",
      "thisMonth": "This Month",
      "lastMonth": "Last Month",
      "to": "to",
      "selectTimeRange": "Select Range",
      "selectDate": "Select Date",
      "last7Days": "Last 7 Days",
      "last30Days": "Last 30 Days",
      "last90Days": "Last 90 Days",
      "last180Days": "Last 180 Days",
      "lastYear": "Last Year",
      "customRange": "Custom Range",
      "amount": "Amount",
      "count": "Count",
      "transactions": "{{count}} transactions",
      "orderAmount": "Order Amount",
      "commissionAmount": "Commission Amount",
      "orderCount": "Order Count",
      "commissionCount": "Commission Count",
      "totalIncome": "Total Income",
      "totalCommission": "Total Commission",
      "totalTransactions": "Total: {{count}} transactions",
      "avgOrderAmount": "Average Order Amount:",
      "commissionRate": "Commission Rate:"
    },
    "traffic": {
      "title": "Traffic Ranking",
      "rank": "Rank",
      "domain": "Domain",
      "todayTraffic": "Today's Traffic",
      "monthlyTraffic": "Monthly Traffic"
    },
    "queue": {
      "title": "Queue Status",
      "jobDetails": "Job Details",
      "status": {
        "description": "Current queue running status",
        "running": "Running Status",
        "normal": "Normal",
        "abnormal": "Abnormal",
        "waitTime": "Current wait time: {{seconds}} seconds",
        "pending": "Pending",
        "processing": "Processing",
        "completed": "Completed",
        "failed": "Failed",
        "cancelled": "Cancelled"
      },
      "details": {
        "description": "Queue processing details",
        "recentJobs": "Recent Jobs",
        "statisticsPeriod": "Statistics Period: {{hours}} hours",
        "jobsPerMinute": "Jobs Per Minute",
        "maxThroughput": "Max Throughput: {{value}}",
        "failedJobs7Days": "Failed Jobs (7 days)",
        "retentionPeriod": "Retention Period: {{hours}} hours",
        "longestRunningQueue": "Longest Running Queue",
        "activeProcesses": "Active Processes",
        "id": "Job ID",
        "type": "Job Type",
        "status": "Status",
        "progress": "Progress",
        "createdAt": "Created At",
        "updatedAt": "Updated At",
        "error": "Error Message",
        "data": "Job Data",
        "result": "Result",
        "duration": "Duration",
        "attempts": "Attempts",
        "nextRetry": "Next Retry",
        "failedJobsDetailTitle": "Failed Jobs Details",
        "viewFailedJobs": "View Failed Jobs",
        "jobDetailTitle": "Job Details",
        "time": "Time",
        "queue": "Queue",
        "name": "Job Name",
        "exception": "Exception",
        "noFailedJobs": "No failed jobs",
        "connection": "Connection",
        "payload": "Job Payload",
        "viewDetail": "View Details",
        "action": "Action"
      },
      "actions": {
        "retry": "Retry",
        "cancel": "Cancel",
        "delete": "Delete",
        "viewDetails": "View Details"
      },
      "empty": "No jobs in queue",
      "loading": "Loading queue status...",
      "error": "Failed to load queue status"
    },
    "systemLog": {
      "title": "System Logs",
      "description": "View system operation logs",
      "viewAll": "View All",
      "level": "Level",
      "time": "Time",
      "message": "Message",
      "logTitle": "Title",
      "method": "Method",
      "action": "Action",
      "context": "Context",
      "search": "Search logs...",
      "noLogs": "No logs available",
      "noInfoLogs": "No info logs available",
      "noWarningLogs": "No warning logs available",
      "noErrorLogs": "No error logs available",
      "noSearchResults": "No matching logs found",
      "detailTitle": "Log Details",
      "viewDetail": "View Details",
      "host": "Host",
      "ip": "IP Address",
      "uri": "URI",
      "requestData": "Request Data",
      "exception": "Exception",
      "totalLogs": "Total logs",
      "tabs": {
        "all": "All",
        "info": "Info",
        "warning": "Warning",
        "error": "Error"
      },
      "filter": {
        "searchAndLevel": "Filter results: {{count}} logs containing \\\"{{keyword}}\\\" with level \\\"{{level}}\\\"",
        "searchOnly": "Search results: {{count}} logs containing \\\"{{keyword}}\\\"",
        "levelOnly": "Filter results: {{count}} logs with level \\\"{{level}}\\\"",
        "reset": "Reset Filters"
      },
      "clearLogs": "Clear Logs",
      "clearDays": "Clear Days",
      "clearDaysDesc": "Clear logs older than how many days (0-365 days, 0 means today)",
      "clearLevel": "Log Level",
      "clearLimit": "Batch Limit",
      "clearLimitDesc": "Batch clear quantity limit (100-10000 records)",
      "clearPreview": "Clear Preview",
      "getStats": "Get Statistics",
      "cutoffDate": "Cutoff Date",
      "willClear": "Will Clear",
      "logsUnit": " logs",
      "clearWarning": "This operation cannot be undone, please proceed with caution!",
      "clearing": "Clearing...",
      "confirmClear": "Confirm Clear",
      "clearSuccess": "Clear completed! {{count}} logs cleared",
      "clearFailed": "Clear failed",
      "getStatsFailed": "Failed to get clear statistics",
      "clearLogsFailed": "Failed to clear logs"
    },
    "common": {
      "refresh": "Refresh",
      "close": "Close",
      "pagination": "Page {{current}}/{{total}}, {{count}} items total"
    },
    "search": {
      "placeholder": "Search menus and functions...",
      "title": "Menu Navigation",
      "noResults": "No results found",
      "loading": "Searching..."
    }
  },
  "route": {
    "title": "Route Management",
    "description": "Manage all route groups, including adding, deleting, and editing operations.",
    "columns": {
      "id": "Group ID",
      "remarks": "Remarks",
      "action": "Action",
      "actions": "Actions",
      "matchRules": "Match {{count}} rules",
      "action_value": {
        "title": "Action Value",
        "dns": "DNS: {{value}}",
        "block": "Block Access",
        "direct": "Direct Connection"
      }
    },
    "actions": {
      "dns": "Resolve using specified DNS server",
      "block": "Block access"
    },
    "form": {
      "add": "Add Route",
      "edit": "Edit Route",
      "create": "Create Route",
      "remarks": "Remarks",
      "remarksPlaceholder": "Please enter remarks",
      "match": "Match Rules",
      "matchPlaceholder": "example.com\n*.example.com",
      "action": "Action",
      "actionPlaceholder": "Please select action",
      "dns": "DNS Server",
      "dnsPlaceholder": "Please enter DNS server",
      "cancel": "Cancel",
      "submit": "Submit",
      "validation": {
        "remarks": "Please enter valid remarks"
      }
    },
    "toolbar": {
      "searchPlaceholder": "Search routes...",
      "reset": "Reset"
    },
    "messages": {
      "deleteConfirm": "Confirm Delete",
      "deleteDescription": "This action will permanently delete this route group and cannot be recovered. Are you sure you want to continue?",
      "deleteButton": "Delete",
      "deleteSuccess": "Deleted Successfully",
      "createSuccess": "Created Successfully",
      "updateSuccess": "Updated Successfully"
    }
  },
  "order": {
    "title": "Order Management",
    "description": "Here you can view user orders, including assignment, viewing, deletion and other operations.",
    "table": {
      "columns": {
        "tradeNo": "Order No.",
        "type": "Type",
        "plan": "Subscription Plan",
        "period": "Period",
        "amount": "Payment Amount",
        "status": "Order Status",
        "commission": "Commission Amount",
        "commissionStatus": "Commission Status",
        "createdAt": "Created At"
      }
    },
    "type": {
      "NEW": "New Purchase",
      "RENEWAL": "Renewal",
      "UPGRADE": "Upgrade",
      "RESET_FLOW": "Reset Traffic"
    },
    "period": {
      "month_price": "Monthly",
      "quarter_price": "Quarterly",
      "half_year_price": "Semi-annually",
      "year_price": "Annually",
      "two_year_price": "2 Years",
      "three_year_price": "3 Years",
      "onetime_price": "One-time",
      "reset_price": "Reset Package"
    },
    "status": {
      "PENDING": "Pending",
      "PROCESSING": "Processing",
      "CANCELLED": "Cancelled",
      "COMPLETED": "Completed",
      "DISCOUNTED": "Discounted",
      "tooltip": "After marking as [Paid], the system will proceed with activation and completion"
    },
    "commission": {
      "PENDING": "Pending",
      "PROCESSING": "Processing",
      "VALID": "Valid",
      "INVALID": "Invalid"
    },
    "actions": {
      "markAsPaid": "Mark as Paid",
      "cancel": "Cancel Order",
      "openMenu": "Open Menu",
      "reset": "Reset"
    },
    "search": {
      "placeholder": "Search orders..."
    },
    "dialog": {
      "title": "Order Information",
      "basicInfo": "Basic Information",
      "amountInfo": "Amount Information",
      "timeInfo": "Time Information",
      "commissionInfo": "Commission Information",
      "commissionStatusActive": "Active",
      "addOrder": "Add Order",
      "assignOrder": "Assign Order",
      "fields": {
        "userEmail": "User Email",
        "orderPeriod": "Order Period",
        "subscriptionPlan": "Subscription Plan",
        "callbackNo": "Callback No.",
        "paymentAmount": "Payment Amount",
        "balancePayment": "Balance Payment",
        "discountAmount": "Discount Amount",
        "refundAmount": "Refund Amount",
        "deductionAmount": "Deduction Amount",
        "createdAt": "Created At",
        "updatedAt": "Updated At",
        "commissionStatus": "Commission Status",
        "commissionAmount": "Commission Amount",
        "actualCommissionAmount": "Actual Commission",
        "inviteUser": "Inviter",
        "inviteUserId": "Inviter ID"
      },
      "placeholders": {
        "email": "Please enter user email",
        "plan": "Please select subscription plan",
        "period": "Please select subscription period",
        "amount": "Please enter payment amount"
      },
      "actions": {
        "cancel": "Cancel",
        "confirm": "Confirm"
      },
      "messages": {
        "addSuccess": "Added successfully"
      }
    }
  },
  "coupon": {
    "title": "Coupon Management",
    "description": "Here you can manage coupons, including adding, viewing, and deleting operations.",
    "table": {
      "columns": {
        "id": "ID",
        "show": "Enable",
        "name": "Coupon Name",
        "type": "Type",
        "code": "Code",
        "limitUse": "Remaining Uses",
        "limitUseWithUser": "Uses Per User",
        "validity": "Validity Period",
        "actions": "Actions"
      },
      "validity": {
        "expired": "Expired {{days}} days ago",
        "notStarted": "Starts in {{days}} days",
        "remaining": "{{days}} days remaining",
        "startTime": "Start Time",
        "endTime": "End Time",
        "unlimited": "Unlimited",
        "noLimit": "No Limit"
      },
      "actions": {
        "edit": "Edit",
        "delete": "Delete",
        "deleteConfirm": {
          "title": "Confirm Delete",
          "description": "This action will permanently delete this coupon and cannot be undone. Are you sure you want to continue?",
          "confirmText": "Delete"
        }
      },
      "toolbar": {
        "search": "Search coupons...",
        "type": "Type",
        "reset": "Reset",
        "types": {
          "1": "Fixed Amount",
          "2": "Percentage"
        }
      }
    },
    "form": {
      "add": "Add Coupon",
      "edit": "Edit Coupon",
      "name": {
        "label": "Coupon Name",
        "placeholder": "Enter coupon name",
        "required": "Please enter coupon name"
      },
      "type": {
        "label": "Coupon Type and Value",
        "placeholder": "Select coupon type"
      },
      "value": {
        "placeholder": "Enter value"
      },
      "validity": {
        "label": "Validity Period",
        "to": "to",
        "endTimeError": "End time must be later than start time"
      },
      "limitUse": {
        "label": "Maximum Uses",
        "placeholder": "Set maximum uses, leave empty for unlimited",
        "description": "Set the total number of times this coupon can be used, leave empty for unlimited uses"
      },
      "limitUseWithUser": {
        "label": "Uses Per User",
        "placeholder": "Set uses per user, leave empty for unlimited",
        "description": "Limit how many times each user can use this coupon, leave empty for unlimited uses per user"
      },
      "limitPeriod": {
        "label": "Subscription Periods",
        "placeholder": "Limit to specific subscription periods, leave empty for no restrictions",
        "description": "Select which subscription periods can use this coupon, leave empty for no period restrictions",
        "empty": "No matching periods found"
      },
      "limitPlan": {
        "label": "Subscription Plans",
        "placeholder": "Limit to specific subscription plans, leave empty for no restrictions",
        "empty": "No matching plans found"
      },
      "code": {
        "label": "Custom Coupon Code",
        "placeholder": "Enter custom code, leave empty for auto-generation",
        "description": "Customize the coupon code, leave empty for auto-generation"
      },
      "generateCount": {
        "label": "Batch Generation Count",
        "placeholder": "Number of coupons to generate, leave empty for single coupon",
        "description": "Generate multiple coupon codes at once, leave empty to generate a single code"
      },
      "submit": {
        "saving": "Saving...",
        "save": "Save"
      },
      "error": {
        "saveFailed": "Failed to save coupon"
      },
      "timeRange": {
        "quickSet": "Quick Set",
        "presets": {
          "1week": "1 Week",
          "2weeks": "2 Weeks",
          "1month": "1 Month",
          "3months": "3 Months",
          "6months": "6 Months",
          "1year": "1 Year"
        }
      }
    },
    "period": {
      "monthly": "Monthly",
      "quarterly": "Quarterly",
      "half_yearly": "Half Yearly",
      "yearly": "Yearly",
      "two_yearly": "Two Yearly",
      "three_yearly": "Three Yearly",
      "onetime": "One Time",
      "reset_traffic": "Reset Traffic"
    }
  },
  "notice": {
    "title": "Notice Management",
    "description": "Here you can configure notices, including adding, deleting, editing and other operations.",
    "table": {
      "columns": {
        "id": "ID",
        "show": "Display Status",
        "title": "Title",
        "actions": "Actions"
      },
      "toolbar": {
        "search": "Search notice title...",
        "reset": "Reset",
        "sort": {
          "edit": "Edit Order",
          "save": "Save Order"
        }
      },
      "actions": {
        "edit": "Edit",
        "delete": {
          "title": "Delete Confirmation",
          "description": "Are you sure you want to delete this notice? This action cannot be undone.",
          "success": "Successfully deleted"
        }
      }
    },
    "form": {
      "add": {
        "title": "Add Notice",
        "button": "Add Notice"
      },
      "edit": {
        "title": "Edit Notice"
      },
      "fields": {
        "title": {
          "label": "Title",
          "placeholder": "Please enter notice title"
        },
        "content": {
          "label": "Content"
        },
        "img_url": {
          "label": "Background Image",
          "placeholder": "Please enter notice background image URL"
        },
        "show": {
          "label": "Display"
        },
        "tags": {
          "label": "Tags",
          "placeholder": "Press Enter to add tags"
        }
      },
      "buttons": {
        "cancel": "Cancel",
        "submit": "Submit",
        "success": "Successfully submitted"
      }
    }
  },
  "theme": {
    "title": "Theme Configuration",
    "description": "Theme configuration, including theme colors, font sizes, etc. If you deploy V2board in a front-end and back-end separated way, theme configuration will not take effect.",
    "upload": {
      "button": "Upload Theme",
      "title": "Upload Theme",
      "description": "Please upload a valid theme package (.zip format). The theme package should contain a complete theme file structure.",
      "dragText": "Drag and drop theme file here, or",
      "clickText": "click to select",
      "supportText": "Supports .zip format theme packages",
      "uploading": "Uploading...",
      "error": {
        "format": "Only ZIP format theme files are supported"
      }
    },
    "preview": {
      "title": "Theme Preview",
      "imageCount": "{{current}} / {{total}}"
    },
    "card": {
      "version": "Version: {{version}}",
      "currentTheme": "Current Theme",
      "activateTheme": "Activate Theme",
      "configureTheme": "Theme Settings",
      "preview": "Preview",
      "delete": {
        "title": "Delete Theme",
        "description": "Are you sure you want to delete this theme? This action cannot be undone.",
        "button": "Delete",
        "error": {
          "active": "Cannot delete the currently active theme"
        }
      }
    },
    "config": {
      "title": "Configure {{name}} Theme",
      "description": "Modify theme styles, layouts, and other display options.",
      "cancel": "Cancel",
      "save": "Save",
      "success": "Settings saved successfully"
    }
  },
  "ticket": {
    "title": "Ticket Management",
    "description": "View and manage user tickets, including viewing, replying, and closing operations.",
    "columns": {
      "id": "Ticket ID",
      "subject": "Subject",
      "level": "Priority",
      "status": "Status",
      "updated_at": "Last Updated",
      "created_at": "Created At",
      "actions": "Actions"
    },
    "status": {
      "closed": "Closed",
      "replied": "Replied",
      "pending": "Pending",
      "processing": "Processing"
    },
    "level": {
      "low": "Low Priority",
      "medium": "Medium Priority",
      "high": "High Priority"
    },
    "filter": {
      "placeholder": "Search {field}...",
      "no_results": "No results found",
      "selected": "{count} selected",
      "clear": "Clear filters"
    },
    "actions": {
      "view_details": "View Details",
      "close_ticket": "Close Ticket",
      "close_confirm_title": "Confirm Close Ticket",
      "close_confirm_description": "Are you sure you want to close this ticket? You won't be able to reply after closing.",
      "close_confirm_button": "Confirm Close",
      "close_success": "Ticket closed successfully",
      "view_ticket": "View Ticket"
    },
    "detail": {
      "no_messages": "No messages yet",
      "created_at": "Created at",
      "user_info": "User Info",
      "traffic_records": "Traffic Records",
      "order_records": "Order Records",
      "input": {
        "closed_placeholder": "Ticket is closed",
        "reply_placeholder": "Type your reply...",
        "sending": "Sending...",
        "send": "Send"
      }
    },
    "list": {
      "title": "Ticket List",
      "search_placeholder": "Search ticket subject or user email",
      "no_tickets": "No pending tickets",
      "no_search_results": "No matching tickets found"
    }
  },
  "server": {
    "title": "Node Configuration",
    "description": "Configure node communication and synchronization settings, including communication key, polling interval, load balancing and other advanced options.",
    "server_token": {
      "title": "Communication Key",
      "description": "The key for communication between Xboard and nodes to prevent unauthorized data access.",
      "placeholder": "Please enter communication key"
    },
    "server_pull_interval": {
      "title": "Node Pull Action Polling Interval",
      "description": "The frequency at which nodes retrieve data from the panel.",
      "placeholder": "Please enter pull interval"
    },
    "server_push_interval": {
      "title": "Node Push Action Polling Interval",
      "description": "The frequency at which nodes push data to the panel.",
      "placeholder": "Please enter push interval"
    },
    "device_limit_mode": {
      "title": "Device Limit Mode",
      "description": "In relaxed mode, multiple nodes from the same IP address count as one device.",
      "strict": "Strict Mode",
      "relaxed": "Relaxed Mode",
      "placeholder": "Please select device limit mode"
    },
    "saving": "Saving...",
    "manage": {
      "title": "Node Management",
      "description": "Manage all nodes, including adding, deleting, editing and other operations."
    },
    "columns": {
      "sort": "Sort",
      "nodeId": "Node ID",
      "show": "Show",
      "node": "Node",
      "address": "Address",
      "onlineUsers": {
        "title": "Online Users",
        "tooltip": "Online users count based on server reporting frequency"
      },
      "rate": {
        "title": "Rate",
        "tooltip": "Traffic billing rate"
      },
      "groups": {
        "title": "Permission Groups",
        "tooltip": "Groups that can subscribe to this node",
        "empty": "--"
      },
      "loadStatus": {
        "title": "Load Status",
        "tooltip": "Server resource usage",
        "noData": "No Data",
        "details": "System Load Details",
        "cpu": "CPU Usage",
        "memory": "Memory Usage",
        "swap": "Swap Usage",
        "disk": "Disk Usage",
        "lastUpdate": "Last Updated"
      },
      "type": "Type",
      "actions": "Actions",
      "copyAddress": "Copy Connection Address",
      "internalPort": "Internal Port",
      "status": {
        "0": "Not Running",
        "1": "Unused or Abnormal",
        "2": "Running Normal"
      },
      "actions_dropdown": {
        "edit": "Edit",
        "copy": "Copy",
        "delete": {
          "title": "Confirm Delete",
          "description": "This action will permanently delete this node and cannot be undone. Are you sure you want to continue?",
          "confirm": "Delete"
        },
        "copy_success": "Copied successfully",
        "delete_success": "Deleted successfully"
      }
    },
    "toolbar": {
      "search": "Search nodes...",
      "type": "Type",
      "reset": "Reset",
      "sort": {
        "tip": "Drag nodes to sort, then click save",
        "edit": "Edit Sort",
        "save": "Save Sort"
      }
    },
    "form": {
      "add_node": "Add Node",
      "edit_node": "Edit Node",
      "new_node": "New Node",
      "name": {
        "label": "Node Name",
        "placeholder": "Please enter node name",
        "error": "Please enter a valid name"
      },
      "rate": {
        "label": "Base Rate",
        "error": "Base rate is required",
        "error_numeric": "Base rate must be a number",
        "error_gte_zero": "Base rate must be greater than or equal to 0"
      },
      "dynamic_rate": {
        "enable_label": "Enable Dynamic Rate",
        "enable_description": "Set different rate multipliers based on time periods",
        "rules_label": "Time Period Rules",
        "add_rule": "Add Rule",
        "rule_title": "Rule {{index}}",
        "start_time": "Start Time",
        "end_time": "End Time",
        "multiplier": "Rate Multiplier",
        "no_rules": "No rules yet, click the button above to add",
        "start_time_error": "Start time is required",
        "end_time_error": "End time is required",
        "multiplier_error": "Rate multiplier is required",
        "multiplier_error_numeric": "Rate multiplier must be a number",
        "multiplier_error_gte_zero": "Rate multiplier must be greater than or equal to 0"
      },
      "code": {
        "label": "Custom Node ID",
        "optional": "(Optional)",
        "placeholder": "Please enter custom node ID"
      },
      "tags": {
        "label": "Node Tags",
        "placeholder": "Press Enter to add tags"
      },
      "groups": {
        "label": "Permission Groups",
        "add": "Add Group",
        "placeholder": "Please select permission groups",
        "empty": "No results found"
      },
      "host": {
        "label": "Node Address",
        "placeholder": "Please enter domain or IP",
        "error": "Node address is required"
      },
      "port": {
        "label": "Connection Port",
        "placeholder": "User connection port",
        "tooltip": "The port that users actually connect to, this is the port number that needs to be filled in the client configuration. If using transit or tunnel, this port may be different from the port that the server actually listens on.",
        "sync": "Sync to server port",
        "error": "Connection port is required"
      },
      "server_port": {
        "label": "Server Port",
        "placeholder": "Enter server port",
        "error": "Server port is required",
        "tooltip": "The actual listening port on the server.",
        "sync": "Sync to server port"
      },
      "parent": {
        "label": "Parent Node",
        "placeholder": "Select parent node",
        "none": "None"
      },
      "route": {
        "label": "Route Groups",
        "placeholder": "Select route groups",
        "empty": "No results found"
      },
      "submit": "Submit",
      "cancel": "Cancel",
      "success": "Submitted successfully"
    },
    "dynamic_form": {
      "shadowsocks": {
        "cipher": {
          "label": "Encryption Method",
          "placeholder": "Select encryption method"
        },
        "obfs": {
          "label": "Obfuscation",
          "placeholder": "Select obfuscation method",
          "none": "None",
          "http": "HTTP"
        },
        "obfs_settings": {
          "path": "Path",
          "host": "Host"
        }
      },
      "vmess": {
        "tls": {
          "label": "TLS",
          "placeholder": "Please select security",
          "disabled": "Disabled",
          "enabled": "Enabled"
        },
        "tls_settings": {
          "server_name": {
            "label": "Server Name Indication (SNI)",
            "placeholder": "Leave empty if not used"
          },
          "allow_insecure": "Allow Insecure?"
        },
        "network": {
          "label": "Transport Protocol",
          "placeholder": "Select transport protocol"
        }
      },
      "trojan": {
        "server_name": {
          "label": "Server Name Indication (SNI)",
          "placeholder": "Used for certificate verification when node address differs from certificate"
        },
        "allow_insecure": "Allow Insecure?",
        "network": {
          "label": "Transport Protocol",
          "placeholder": "Select transport protocol"
        }
      },
      "hysteria": {
        "version": {
          "label": "Protocol Version",
          "placeholder": "Protocol version"
        },
        "alpn": {
          "label": "ALPN",
          "placeholder": "ALPN"
        },
        "obfs": {
          "label": "Obfuscation",
          "type": {
            "label": "Obfuscation Implementation",
            "placeholder": "Select obfuscation implementation",
            "salamander": "Salamander"
          },
          "password": {
            "label": "Obfuscation Password",
            "placeholder": "Please enter obfuscation password",
            "generate_success": "Obfuscation password generated successfully"
          }
        },
        "tls": {
          "server_name": {
            "label": "Server Name Indication (SNI)",
            "placeholder": "Used for certificate verification when node address differs from certificate"
          },
          "allow_insecure": "Allow Insecure?"
        },
        "bandwidth": {
          "up": {
            "label": "Upload Bandwidth",
            "placeholder": "Please enter upload bandwidth",
            "suffix": "Mbps",
            "bbr_tip": ", leave empty to use BBR"
          },
          "down": {
            "label": "Download Bandwidth",
            "placeholder": "Please enter download bandwidth",
            "suffix": "Mbps",
            "bbr_tip": ", leave empty to use BBR"
          }
        }
      },
      "vless": {
        "tls": {
          "label": "Security",
          "placeholder": "Please select security",
          "none": "None",
          "tls": "TLS",
          "reality": "Reality"
        },
        "tls_settings": {
          "server_name": {
            "label": "Server Name Indication (SNI)",
            "placeholder": "Leave empty if not used"
          },
          "allow_insecure": "Allow Insecure?"
        },
        "reality_settings": {
          "server_name": {
            "label": "Destination Site (dest)",
            "placeholder": "e.g., example.com"
          },
          "server_port": {
            "label": "Port",
            "placeholder": "e.g., 443"
          },
          "allow_insecure": "Allow Insecure?",
          "private_key": {
            "label": "Private Key"
          },
          "public_key": {
            "label": "Public Key"
          },
          "short_id": {
            "label": "Short ID",
            "placeholder": "Optional, length must be even, max 16 characters",
            "description": "List of shortIds available to clients, can be used to distinguish different clients, using hexadecimal characters 0-f",
            "generate": "Generate Short ID",
            "success": "Short ID generated successfully"
          },
          "key_pair": {
            "generate": "Generate Key Pair",
            "success": "Key pair generated successfully",
            "error": "Failed to generate key pair"
          }
        },
        "network": {
          "label": "Transport Protocol",
          "placeholder": "Select transport protocol"
        },
        "flow": {
          "label": "Flow Control",
          "placeholder": "Select flow control"
        }
      },
      "tuic": {
        "version": {
          "label": "Protocol Version",
          "placeholder": "Select TUIC Version"
        },
        "password": {
          "label": "Password",
          "placeholder": "Enter Password",
          "generate_success": "Password Generated Successfully"
        },
        "congestion_control": {
          "label": "Congestion Control",
          "placeholder": "Select Congestion Control Algorithm"
        },
        "udp_relay_mode": {
          "label": "UDP Relay Mode",
          "placeholder": "Select UDP Relay Mode"
        },
        "tls": {
          "server_name": {
            "label": "Server Name Indication (SNI)",
            "placeholder": "Used for certificate verification when domain differs from node address"
          },
          "allow_insecure": "Allow Insecure?",
          "alpn": {
            "label": "ALPN",
            "placeholder": "Select ALPN Protocols",
            "empty": "No ALPN Protocols Available"
          }
        }
      },
      "socks": {
        "version": {
          "label": "Protocol Version",
          "placeholder": "Select SOCKS Version"
        },
        "tls": {
          "label": "TLS",
          "placeholder": "Please select security",
          "disabled": "Disabled",
          "enabled": "Enabled"
        },
        "tls_settings": {
          "server_name": {
            "label": "Server Name Indication (SNI)",
            "placeholder": "Leave empty if not used"
          },
          "allow_insecure": "Allow Insecure?"
        },
        "network": {
          "label": "Transport Protocol",
          "placeholder": "Select transport protocol"
        }
      },
      "naive": {
        "tls_settings": {
          "server_name": {
            "label": "Server Name Indication (SNI)",
            "placeholder": "Used for certificate verification when domain differs from node address"
          },
          "allow_insecure": "Allow Insecure"
        },
        "tls": {
          "label": "TLS",
          "placeholder": "Please select security",
          "disabled": "Disabled",
          "enabled": "Enabled",
          "server_name": {
            "label": "Server Name Indication (SNI)",
            "placeholder": "Used for certificate verification when domain differs from node address"
          },
          "allow_insecure": "Allow Insecure"
        }
      },
      "http": {
        "tls_settings": {
          "server_name": {
            "label": "Server Name Indication (SNI)",
            "placeholder": "Used for certificate verification when domain differs from node address"
          },
          "allow_insecure": "Allow Insecure"
        },
        "tls": {
          "label": "TLS",
          "placeholder": "Please select security",
          "disabled": "Disabled",
          "enabled": "Enabled",
          "server_name": {
            "label": "Server Name Indication (SNI)",
            "placeholder": "Used for certificate verification when domain differs from node address"
          },
          "allow_insecure": "Allow Insecure"
        }
      },
      "mieru": {
        "transport": {
          "label": "Transport Protocol",
          "placeholder": "Select transport protocol"
        },
        "multiplexing": {
          "label": "Multiplexing",
          "placeholder": "Select multiplexing level",
          "MULTIPLEXING_OFF": "Disabled",
          "MULTIPLEXING_LOW": "Low",
          "MULTIPLEXING_MIDDLE": "Medium",
          "MULTIPLEXING_HIGH": "High"
        }
      }
    },
    "network_settings": {
      "edit_protocol": "Edit Protocol",
      "edit_protocol_config": "Edit Protocol Configuration",
      "use_template": "Use {{template}} Template",
      "json_config_placeholder": "Please enter JSON configuration",
      "json_config_placeholder_with_template": "Please enter JSON configuration or select template above",
      "validation": {
        "must_be_object": "Configuration must be a JSON object",
        "invalid_json": "Invalid JSON format"
      },
      "errors": {
        "save_failed": "Error occurred while saving"
      }
    },
    "common": {
      "cancel": "Cancel",
      "confirm": "Confirm"
    }
  },
  "user": {
    "manage": {
      "title": "User Management",
      "description": "Here you can manage users, including adding, deleting, editing, and querying operations."
    },
    "columns": {
      "is_admin": "Admin",
      "is_staff": "Staff",
      "id": "ID",
      "email": "Email",
      "online_count": "Online Devices",
      "status": "Status",
      "subscription": "Subscription",
      "group": "Group",
      "used_traffic": "Used Traffic",
      "total_traffic": "Total Traffic",
      "expire_time": "Expire Time",
      "balance": "Balance",
      "commission": "Commission",
      "register_time": "Register Time",
      "actions": "Actions",
      "device_limit": {
        "unlimited": "No device limit",
        "limited": "Maximum {{count}} devices allowed"
      },
      "status_text": {
        "normal": "Normal",
        "banned": "Banned"
      },
      "online_status": {
        "online": "Currently Online",
        "never": "Never Online",
        "last_online": "Last Online: {{time}}",
        "offline_duration": {
          "days": "Offline Duration: {{count}}d",
          "hours": "Offline Duration: {{count}}h",
          "minutes": "Offline Duration: {{count}}m",
          "seconds": "Offline Duration: {{count}}s"
        }
      },
      "expire_status": {
        "permanent": "Permanent",
        "expired": "Expired {{days}} days ago",
        "remaining": "{{days}} days remaining"
      },
      "actions_menu": {
        "edit": "Edit",
        "assign_order": "Assign Order",
        "copy_url": "Copy Subscribe URL",
        "reset_secret": "Reset UUID & URL",
        "orders": "Orders",
        "invites": "Invites",
        "traffic_records": "Traffic Records",
        "reset_traffic": "Reset Traffic",
        "delete": "Delete",
        "delete_confirm_title": "Confirm Delete User",
        "delete_confirm_description": "This action will permanently delete user {{email}} and all associated data, including orders, coupons, traffic records, and support tickets. This action cannot be undone. Do you want to continue?"
      }
    },
    "filter": {
      "selected": "{{count}} selected",
      "no_results": "No results found.",
      "clear": "Clear filters",
      "search_placeholder": "Search...",
      "email_search": "Search user email...",
      "advanced": "Advanced Filter",
      "reset": "Reset Filter",
      "sheet": {
        "title": "Advanced Filter",
        "description": "Add one or more filter conditions to find users precisely",
        "conditions": "Filter Conditions",
        "add": "Add Condition",
        "condition": "Condition {{number}}",
        "field": "Select Field",
        "operator": "Select Operator",
        "value": "Enter Value",
        "value_number": "Enter Value ({{unit}})",
        "reset": "Reset",
        "apply": "Apply Filter"
      },
      "fields": {
        "email": "Email",
        "id": "User ID",
        "plan_id": "Subscription",
        "transfer_enable": "Traffic",
        "total_used": "Used Traffic",
        "online_count": "Online Devices",
        "expired_at": "Expire Time",
        "uuid": "UUID",
        "token": "Token",
        "banned": "Account Status",
        "remark": "Remark",
        "inviter_email": "Inviter Email",
        "invite_user_id": "Inviter ID",
        "is_admin": "Admin",
        "is_staff": "Staff"
      },
      "operators": {
        "contains": "Contains",
        "eq": "Equals",
        "gt": "Greater Than",
        "lt": "Less Than"
      },
      "status": {
        "normal": "Normal",
        "banned": "Banned"
      },
      "boolean": {
        "true": "Yes",
        "false": "No"
      }
    },
    "generate": {
      "button": "Create User",
      "title": "Create User",
      "form": {
        "email": "Email",
        "email_prefix": "Account (leave empty for batch generation)",
        "email_domain": "Domain",
        "password": "Password",
        "password_placeholder": "Leave empty to use email as password",
        "expire_time": "Expire Time",
        "expire_time_placeholder": "Select user expire date, leave empty for permanent",
        "permanent": "Permanent",
        "subscription": "Subscription Plan",
        "subscription_none": "None",
        "generate_count": "Generate Count",
        "generate_count_placeholder": "Enter count for batch generation",
        "cancel": "Cancel",
        "submit": "Generate",
        "success": "Generated successfully",
        "download_csv": "Export as CSV file"
      }
    },
    "edit": {
      "button": "Edit User Info",
      "title": "User Management",
      "form": {
        "email": "Email",
        "email_placeholder": "Please enter email",
        "inviter_email": "Inviter Email",
        "inviter_email_placeholder": "Please enter email",
        "password": "Password",
        "password_placeholder": "Enter new password if you want to change it",
        "balance": "Balance",
        "balance_placeholder": "Please enter balance",
        "commission_balance": "Commission Balance",
        "commission_balance_placeholder": "Please enter commission balance",
        "upload": "Upload Traffic",
        "upload_placeholder": "Upload traffic",
        "download": "Download Traffic",
        "download_placeholder": "Download traffic",
        "total_traffic": "Total Traffic",
        "total_traffic_placeholder": "Please enter traffic",
        "expire_time": "Expire Time",
        "expire_time_placeholder": "Select user expire date, leave empty for permanent",
        "expire_time_specific": "Specific Time",
        "expire_time_today": "Set to end of today",
        "expire_time_permanent": "Permanent",
        "expire_time_1month": "One Month",
        "expire_time_3months": "Three Months",
        "expire_time_confirm": "Confirm",
        "subscription": "Subscription Plan",
        "subscription_none": "None",
        "account_status": "Account Status",
        "commission_type": "Commission Type",
        "commission_type_system": "Follow System Settings",
        "commission_type_cycle": "Cycle Commission",
        "commission_type_onetime": "One-time Commission",
        "commission_rate": "Commission Rate",
        "commission_rate_placeholder": "Leave empty to follow site commission rate",
        "discount": "Exclusive Discount",
        "discount_placeholder": "Leave empty for no exclusive discount",
        "speed_limit": "Speed Limit",
        "speed_limit_placeholder": "Leave empty for no speed limit",
        "device_limit": "Device Limit",
        "device_limit_placeholder": "Leave empty for no device limit",
        "is_admin": "Is Admin",
        "is_staff": "Is Staff",
        "remarks": "Remarks",
        "remarks_placeholder": "Please enter remarks here",
        "cancel": "Cancel",
        "submit": "Submit",
        "success": "Modified successfully"
      }
    },
    "actions": {
      "title": "Actions",
      "send_email": "Send Email",
      "export_csv": "Export CSV",
      "traffic_reset_stats": "Traffic Reset Stats",
      "batch_ban": "Batch Ban",
      "confirm_ban": {
        "title": "Confirm Batch Ban",
        "filtered_description": "This action will ban all users that match your current filters. This action cannot be undone.",
        "all_description": "This action will ban all users in the system. This action cannot be undone.",
        "cancel": "Cancel",
        "confirm": "Confirm Ban",
        "banning": "Banning..."
      }
    },
    "messages": {
      "success": "Success",
      "error": "Error",
      "export": {
        "success": "Export successful",
        "failed": "Export failed"
      },
      "batch_ban": {
        "success": "Batch ban successful",
        "failed": "Batch ban failed"
      },
      "send_mail": {
        "success": "Email sent successfully",
        "failed": "Failed to send email",
        "required_fields": "Please fill in all required fields"
      }
    },
    "traffic_reset": {
      "title": "Traffic Reset",
      "description": "Reset traffic usage for user {{email}}",
      "tabs": {
        "reset": "Reset Traffic",
        "history": "Reset History"
      },
      "user_info": "User Information",
      "warning": {
        "title": "Important Notice",
        "irreversible": "Traffic reset operation is irreversible, please proceed with caution",
        "reset_to_zero": "After reset, user's upload and download traffic will be cleared to zero",
        "logged": "All reset operations will be logged in the system"
      },
      "reason": {
        "label": "Reset Reason",
        "placeholder": "Please enter the reason for traffic reset (optional)",
        "optional": "This field is optional and used to record the reason for reset"
      },
      "confirm_reset": "Confirm Reset",
      "resetting": "Resetting...",
      "reset_success": "Traffic reset successful",
      "reset_failed": "Traffic reset failed",
      "history": {
        "summary": "Reset Overview",
        "reset_count": "Reset Count",
        "last_reset": "Last Reset",
        "next_reset": "Next Reset",
        "never": "Never Reset",
        "no_schedule": "No Scheduled Reset",
        "records": "Reset Records",
        "recent_records": "Recent 10 Reset Records",
        "no_records": "No reset records",
        "reset_time": "Reset Time",
        "traffic_cleared": "Traffic Cleared"
      },
      "stats": {
        "title": "Traffic Reset Statistics",
        "description": "View system traffic reset statistics",
        "time_range": "Statistics Time Range",
        "total_resets": "Total Resets",
        "auto_resets": "Auto Resets",
        "manual_resets": "Manual Resets",
        "cron_resets": "Cron Resets",
        "in_period": "In the last {{days}} days",
        "breakdown": "Reset Type Breakdown",
        "breakdown_description": "Percentage breakdown of different reset operation types",
        "auto_percentage": "Auto Reset Percentage",
        "manual_percentage": "Manual Reset Percentage",
        "cron_percentage": "Cron Reset Percentage",
        "days_options": {
          "week": "Last Week",
          "month": "Last Month",
          "quarter": "Last Quarter",
          "year": "Last Year"
        }
      }
    },
    "traffic_reset_logs": {
      "title": "Traffic Reset Logs",
      "description": "View detailed records of all traffic reset operations in the system",
      "columns": {
        "id": "Log ID",
        "user": "User",
        "reset_type": "Reset Type",
        "trigger_source": "Trigger Source",
        "cleared_traffic": "Cleared Traffic",
        "cleared": "Cleared",
        "upload": "Upload",
        "download": "Download",
        "reset_time": "Reset Time",
        "log_time": "Log Time"
      },
      "filters": {
        "search_user": "Search user email...",
        "reset_type": "Reset Type",
        "trigger_source": "Trigger Source",
        "all_types": "All Types",
        "all_sources": "All Sources",
        "start_date": "Start Date",
        "end_date": "End Date",
        "apply_date": "Apply Filter",
        "reset": "Reset Filter",
        "filter_title": "Filter Options",
        "filter_description": "Set filter conditions to find specific traffic reset records",
        "reset_types": {
          "monthly": "Monthly Reset",
          "first_day_month": "First Day of Month Reset",
          "yearly": "Yearly Reset",
          "first_day_year": "First Day of Year Reset",
          "manual": "Manual Reset"
        },
        "trigger_sources": {
          "auto": "Auto Trigger",
          "manual": "Manual Trigger",
          "cron": "Cron Job"
        }
      },
      "actions": {
        "export": "Export Logs",
        "exporting": "Exporting...",
        "export_success": "Export successful",
        "export_failed": "Export failed"
      },
      "trigger_descriptions": {
        "manual": "Manually executed traffic reset by administrator",
        "cron": "Automatically executed by system scheduled task",
        "auto": "Automatically triggered by system based on conditions",
        "other": "Triggered by other methods"
      }
    },
    "send_mail": {
      "title": "Send Email",
      "description": "Send email to selected or filtered users",
      "subject": "Subject",
      "content": "Content",
      "sending": "Sending...",
      "send": "Send"
    }
  },
  "subscribe": {
    "plan": {
      "title": "Subscription Plans",
      "add": "Add Plan",
      "search": "Search plans...",
      "sort": {
        "edit": "Edit Sort",
        "save": "Save Sort"
      },
      "columns": {
        "id": "ID",
        "show": "Show",
        "sell": "Sell",
        "renew": "Renew",
        "renew_tooltip": "Whether existing users can renew when the subscription stops selling",
        "name": "Name",
        "stats": "Statistics",
        "group": "Permission Group",
        "price": "Price",
        "actions": "Actions",
        "edit": "Edit",
        "delete": "Delete",
        "delete_confirm": {
          "title": "Confirm Delete",
          "description": "This action will permanently delete this subscription and cannot be undone. Are you sure you want to continue?",
          "success": "Successfully deleted"
        },
        "price_period": {
          "monthly": "Monthly",
          "quarterly": "Quarterly",
          "half_yearly": "Half Yearly",
          "yearly": "Yearly",
          "two_yearly": "Two Years",
          "three_yearly": "Three Years",
          "onetime": "One Time",
          "reset_traffic": "Reset Traffic",
          "unit": {
            "month": "/month",
            "quarter": "/quarter",
            "half_year": "/half year",
            "year": "/year",
            "two_year": "/2 years",
            "three_year": "/3 years",
            "times": "/time"
          }
        }
      },
      "form": {
        "add_title": "Add Plan",
        "edit_title": "Edit Plan",
        "name": {
          "label": "Plan Name",
          "placeholder": "Enter plan name"
        },
        "group": {
          "label": "Server Group",
          "add": "Add Group",
          "placeholder": "Select server group"
        },
        "transfer": {
          "label": "Traffic",
          "placeholder": "Enter traffic limit",
          "unit": "GB"
        },
        "speed": {
          "label": "Speed Limit",
          "placeholder": "Enter speed limit",
          "unit": "Mbps"
        },
        "price": {
          "title": "Price Settings",
          "base_price": "Base Price",
          "clear": {
            "button": "Clear",
            "tooltip": "Clear all prices"
          },
          "period": {
            "monthly": "Monthly",
            "months": "{{count}} Months"
          },
          "onetime_desc": "One-time traffic package, no time limit",
          "reset_desc": "Reset traffic package, can be used multiple times"
        },
        "device": {
          "label": "Device Limit",
          "placeholder": "Enter device limit",
          "unit": "Devices"
        },
        "capacity": {
          "label": "Capacity Limit",
          "placeholder": "Enter capacity limit",
          "unit": "Users"
        },
        "tags": {
          "label": "Tags",
          "placeholder": "Enter a tag and press Enter to confirm"
        },
        "reset_method": {
          "label": "Traffic Reset Method",
          "placeholder": "Select reset method",
          "description": "Traffic reset method will determine how the traffic is reset",
          "options": {
            "follow_system": "Follow System Settings",
            "monthly_first": "Monthly First Day",
            "monthly_reset": "Monthly Purchase Day",
            "no_reset": "No Reset",
            "yearly_first": "Yearly First Day",
            "yearly_reset": "Yearly Purchase Day"
          }
        },
        "content": {
          "label": "Plan Description",
          "placeholder": "Enter plan description",
          "description": "Support Markdown format",
          "preview": "Preview",
          "preview_button": {
            "show": "Show Preview",
            "hide": "Hide Preview"
          },
          "template": {
            "button": "Use Template",
            "tooltip": "Use default template",
            "content": "## Plan Details\n\n- Data: {{transfer}} GB\n- Speed Limit: {{speed}} Mbps\n- Concurrent Devices: {{devices}}\n\n## Service Information\n\n1. Data {{reset_method}}\n2. Multi-platform Support\n3. 24/7 Technical Support"
          }
        },
        "force_update": {
          "label": "Force Update User Plans"
        },
        "submit": {
          "cancel": "Cancel",
          "submit": "Submit",
          "submitting": "Submitting...",
          "success": {
            "add": "Plan added successfully",
            "update": "Plan updated successfully"
          },
          "error": {
            "validation": "Form validation failed. Please check for errors and try again."
          }
        }
      },
      "page": {
        "description": "Here you can configure subscription plans, including adding, deleting, and editing operations."
      }
    }
  },
  "auth": {
    "signIn": {
      "title": "Sign In",
      "description": "Enter your email and password to sign in",
      "email": "Email",
      "emailPlaceholder": "name@example.com",
      "password": "Password",
      "passwordPlaceholder": "Enter your password",
      "forgotPassword": "Forgot Password?",
      "submit": "Sign In",
      "rememberMe": "Remember me",
      "resetPassword": {
        "title": "Reset Password",
        "description": "Execute the following command in the site directory to reset your password",
        "command": "php artisan reset:password admin-email"
      },
      "validation": {
        "emailRequired": "Please enter your email address",
        "emailInvalid": "Please enter a valid email address",
        "passwordRequired": "Please enter your password",
        "passwordLength": "Password must be at least 7 characters"
      }
    }
  },
  "sidebar": {
    "dashboard": "Dashboard",
    "systemManagement": "System Management",
    "systemConfig": "System Configuration",
    "themeConfig": "Theme Configuration",
    "noticeManagement": "Notice Management",
    "paymentConfig": "Payment Configuration",
    "knowledgeManagement": "Knowledge Base",
    "nodeManagement": "Node Management",
    "permissionGroupManagement": "Permission Groups",
    "routeManagement": "Route Management",
    "subscriptionManagement": "Subscription Management",
    "planManagement": "Plan Management",
    "orderManagement": "Order Management",
    "couponManagement": "Coupon Management",
    "userManagement": "User Management",
    "ticketManagement": "Ticket Management"
  }
};