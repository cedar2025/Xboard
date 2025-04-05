export default {
  "subscribe": {
    "plan": {
      "title": "サブスクリプションプラン",
      "add": "プランを追加",
      "search": "プランを検索...",
      "sort": {
        "edit": "並び順を編集",
        "save": "並び順を保存"
      },
      "columns": {
        "id": "ID",
        "show": "表示",
        "sell": "販売",
        "renew": "更新",
        "renew_tooltip": "サブスクリプションの販売停止時に既存ユーザーが更新できるかどうか",
        "name": "名前",
        "language": "言語",
        "stats": "統計",
        "group": "権限グループ",
        "price": "価格",
        "actions": "操作",
        "edit": "編集",
        "delete": "削除",
        "translations": "翻訳",
        "delete_confirm": {
          "title": "削除の確認",
          "description": "この操作はこのサブスクリプションを完全に削除し、元に戻すことはできません。続行しますか？",
          "success": "正常に削除されました"
        },
        "price_period": {
          "monthly": "月額",
          "quarterly": "四半期",
          "half_yearly": "半年",
          "yearly": "年額",
          "two_yearly": "2年",
          "three_yearly": "3年",
          "onetime": "一回限り",
          "reset_traffic": "トラフィックリセット",
          "unit": {
            "month": "/月",
            "quarter": "/四半期",
            "half_year": "/半年",
            "year": "/年",
            "two_year": "/2年",
            "three_year": "/3年",
            "times": "/回"
          }
        }
      },
      "form": {
        "add_title": "プランを追加",
        "edit_title": "プランを編集",
        "name": {
          "label": "プラン名",
          "placeholder": "プラン名を入力"
        },
        "language": {
          "label": "言語",
          "placeholder": "言語を選択",
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
          "label": "権限グループ",
          "placeholder": "権限グループを選択",
          "add": "グループを追加"
        },
        "transfer": {
          "label": "トラフィック",
          "placeholder": "トラフィック制限を入力",
          "unit": "GB"
        },
        "speed": {
          "label": "速度制限",
          "placeholder": "速度制限を入力",
          "unit": "Mbps"
        },
        "price": {
          "title": "価格設定",
          "base_price": "基本月額",
          "clear": {
            "button": "クリア",
            "tooltip": "すべての価格をクリア"
          }
        },
        "device": {
          "label": "デバイス制限",
          "placeholder": "デバイス制限を入力",
          "unit": "デバイス"
        },
        "capacity": {
          "label": "容量制限",
          "placeholder": "容量制限を入力",
          "unit": "ユーザー"
        },
        "reset_method": {
          "label": "トラフィックリセット方法",
          "placeholder": "リセット方法を選択",
          "description": "トラフィックリセット方法は、トラフィックのリセット方法を決定します",
          "options": {
            "follow_system": "システム設定に従う",
            "monthly_first": "毎月1日",
            "monthly_reset": "毎月購入日",
            "no_reset": "リセットなし",
            "yearly_first": "毎年1月1日",
            "yearly_reset": "毎年購入日"
          }
        },
        "content": {
          "label": "プラン説明",
          "placeholder": "プラン説明を入力",
          "description": "Markdown形式をサポート",
          "preview": "プレビュー",
          "preview_button": {
            "show": "プレビューを表示",
            "hide": "プレビューを隠す"
          },
          "template": {
            "button": "テンプレートを使用",
            "tooltip": "デフォルトテンプレートを使用",
            "content": "## プランの詳細\n\n- データ: {{transfer}} GB\n- 速度制限: {{speed}} Mbps\n- 同時接続デバイス: {{devices}}\n\n## サービス情報\n\n1. データ {{reset_method}}\n2. マルチプラットフォーム対応\n3. 24時間365日の技術サポート"
          }
        },
        "force_update": {
          "label": "ユーザープランを強制更新"
        },
        "submit": {
          "cancel": "キャンセル",
          "submit": "送信",
          "submitting": "送信中...",
          "success": {
            "add": "プランが正常に追加されました",
            "update": "プランが正常に更新されました"
          }
        }
      },
      "page": {
        "description": "ここでサブスクリプションプランを設定できます。追加、削除、編集の操作が可能です。"
      }
    }
  }
};
 