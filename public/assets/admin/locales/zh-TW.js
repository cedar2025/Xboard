export default {
  "subscribe": {
    "plan": {
      "title": "訂閱方案",
      "add": "新增方案",
      "search": "搜尋方案...",
      "sort": {
        "edit": "編輯排序",
        "save": "儲存排序"
      },
      "columns": {
        "id": "ID",
        "show": "顯示",
        "sell": "銷售",
        "renew": "續費",
        "renew_tooltip": "當訂閱停止銷售時，現有用戶是否可以續費",
        "name": "名稱",
        "language": "語言",
        "stats": "統計",
        "group": "權限組",
        "price": "價格",
        "actions": "操作",
        "edit": "編輯",
        "delete": "刪除",
        "translations": "翻譯",
        "delete_confirm": {
          "title": "確認刪除",
          "description": "此操作將永久刪除此訂閱，且無法撤銷。是否繼續？",
          "success": "刪除成功"
        },
        "price_period": {
          "monthly": "月付",
          "quarterly": "季付",
          "half_yearly": "半年",
          "yearly": "年付",
          "two_yearly": "兩年",
          "three_yearly": "三年",
          "onetime": "一次性",
          "reset_traffic": "重置流量",
          "unit": {
            "month": "/月",
            "quarter": "/季",
            "half_year": "/半年",
            "year": "/年",
            "two_year": "/2年",
            "three_year": "/3年",
            "times": "/次"
          }
        }
      },
      "form": {
        "add_title": "新增方案",
        "edit_title": "編輯方案",
        "name": {
          "label": "方案名稱",
          "placeholder": "輸入方案名稱"
        },
        "language": {
          "label": "語言",
          "placeholder": "選擇語言",
          "options": {
            "en-US": "English",
            "ja-JP": "日本語",
            "ko-KR": "한국어",
            "vi-VN": "Tiếng Việt",
            "zh-CN": "简体中文",
            "zh-TW": "繁體中文"
          }
        },
        "group": {
          "label": "權限組",
          "placeholder": "選擇權限組",
          "add": "新增組"
        },
        "transfer": {
          "label": "流量",
          "placeholder": "輸入流量限制",
          "unit": "GB"
        },
        "speed": {
          "label": "速度限制",
          "placeholder": "輸入速度限制",
          "unit": "Mbps"
        },
        "price": {
          "title": "價格設定",
          "base_price": "基本月費",
          "clear": {
            "button": "清除",
            "tooltip": "清除所有價格"
          }
        },
        "device": {
          "label": "設備限制",
          "placeholder": "輸入設備限制",
          "unit": "設備"
        },
        "capacity": {
          "label": "容量限制",
          "placeholder": "輸入容量限制",
          "unit": "用戶"
        },
        "reset_method": {
          "label": "流量重置方式",
          "placeholder": "選擇重置方式",
          "description": "流量重置方式將決定流量如何重置",
          "options": {
            "follow_system": "跟隨系統設定",
            "monthly_first": "每月1日",
            "monthly_reset": "每月購買日",
            "no_reset": "不重置",
            "yearly_first": "每年1月1日",
            "yearly_reset": "每年購買日"
          }
        },
        "content": {
          "label": "方案說明",
          "placeholder": "輸入方案說明",
          "description": "支援 Markdown 格式",
          "preview": "預覽",
          "preview_button": {
            "show": "顯示預覽",
            "hide": "隱藏預覽"
          },
          "template": {
            "button": "使用範本",
            "tooltip": "使用預設範本",
            "content": "## 方案詳情\n\n- 流量：{{transfer}} GB\n- 速度限制：{{speed}} Mbps\n- 同時連接設備：{{devices}}\n\n## 服務資訊\n\n1. 流量 {{reset_method}}\n2. 多平台支援\n3. 24/7 技術支援"
          }
        },
        "force_update": {
          "label": "強制更新用戶方案"
        },
        "submit": {
          "cancel": "取消",
          "submit": "提交",
          "submitting": "提交中...",
          "success": {
            "add": "方案新增成功",
            "update": "方案更新成功"
          }
        }
      },
      "page": {
        "description": "在這裡您可以設定訂閱方案，包括新增、刪除和編輯操作。"
      }
    }
  }
}; 