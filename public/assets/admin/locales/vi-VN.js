export default {
  "subscribe": {
    "plan": {
      "title": "Gói đăng ký",
      "add": "Thêm gói",
      "search": "Tìm kiếm gói...",
      "sort": {
        "edit": "Sửa thứ tự",
        "save": "Lưu thứ tự"
      },
      "columns": {
        "id": "ID",
        "show": "Hiển thị",
        "sell": "Bán",
        "renew": "Gia hạn",
        "renew_tooltip": "Người dùng hiện tại có thể gia hạn khi gói ngừng bán hay không",
        "name": "Tên",
        "language": "Ngôn ngữ",
        "stats": "Thống kê",
        "group": "Nhóm quyền",
        "price": "Giá",
        "actions": "Thao tác",
        "edit": "Sửa",
        "delete": "Xóa",
        "translations": "Bản dịch",
        "delete_confirm": {
          "title": "Xác nhận xóa",
          "description": "Hành động này sẽ xóa vĩnh viễn gói đăng ký này và không thể hoàn tác. Bạn có chắc chắn muốn tiếp tục?",
          "success": "Đã xóa thành công"
        },
        "price_period": {
          "monthly": "Hàng tháng",
          "quarterly": "Hàng quý",
          "half_yearly": "Nửa năm",
          "yearly": "Hàng năm",
          "two_yearly": "2 năm",
          "three_yearly": "3 năm",
          "onetime": "Một lần",
          "reset_traffic": "Đặt lại lưu lượng",
          "unit": {
            "month": "/tháng",
            "quarter": "/quý",
            "half_year": "/nửa năm",
            "year": "/năm",
            "two_year": "/2 năm",
            "three_year": "/3 năm",
            "times": "/lần"
          }
        }
      },
      "form": {
        "add_title": "Thêm gói",
        "edit_title": "Sửa gói",
        "name": {
          "label": "Tên gói",
          "placeholder": "Nhập tên gói"
        },
        "language": {
          "label": "Ngôn ngữ",
          "placeholder": "Chọn ngôn ngữ",
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
          "label": "Nhóm quyền",
          "placeholder": "Chọn nhóm quyền",
          "add": "Thêm nhóm"
        },
        "transfer": {
          "label": "Lưu lượng",
          "placeholder": "Nhập giới hạn lưu lượng",
          "unit": "GB"
        },
        "speed": {
          "label": "Giới hạn tốc độ",
          "placeholder": "Nhập giới hạn tốc độ",
          "unit": "Mbps"
        },
        "price": {
          "title": "Cài đặt giá",
          "base_price": "Giá cơ bản hàng tháng",
          "clear": {
            "button": "Xóa",
            "tooltip": "Xóa tất cả giá"
          }
        },
        "device": {
          "label": "Giới hạn thiết bị",
          "placeholder": "Nhập giới hạn thiết bị",
          "unit": "thiết bị"
        },
        "capacity": {
          "label": "Giới hạn dung lượng",
          "placeholder": "Nhập giới hạn dung lượng",
          "unit": "người dùng"
        },
        "reset_method": {
          "label": "Phương thức đặt lại lưu lượng",
          "placeholder": "Chọn phương thức đặt lại",
          "description": "Phương thức đặt lại lưu lượng sẽ xác định cách lưu lượng được đặt lại",
          "options": {
            "follow_system": "Theo cài đặt hệ thống",
            "monthly_first": "Ngày 1 hàng tháng",
            "monthly_reset": "Ngày mua hàng tháng",
            "no_reset": "Không đặt lại",
            "yearly_first": "Ngày 1 tháng 1 hàng năm",
            "yearly_reset": "Ngày mua hàng năm"
          }
        },
        "content": {
          "label": "Mô tả gói",
          "placeholder": "Nhập mô tả gói",
          "description": "Hỗ trợ định dạng Markdown",
          "preview": "Xem trước",
          "preview_button": {
            "show": "Hiện xem trước",
            "hide": "Ẩn xem trước"
          },
          "template": {
            "button": "Sử dụng mẫu",
            "tooltip": "Sử dụng mẫu mặc định",
            "content": "## Chi tiết gói\n\n- Dữ liệu: {{transfer}} GB\n- Giới hạn tốc độ: {{speed}} Mbps\n- Thiết bị đồng thời: {{devices}}\n\n## Thông tin dịch vụ\n\n1. Dữ liệu {{reset_method}}\n2. Hỗ trợ đa nền tảng\n3. Hỗ trợ kỹ thuật 24/7"
          }
        },
        "force_update": {
          "label": "Cập nhật gói người dùng"
        },
        "submit": {
          "cancel": "Hủy",
          "submit": "Gửi",
          "submitting": "Đang gửi...",
          "success": {
            "add": "Đã thêm gói thành công",
            "update": "Đã cập nhật gói thành công"
          }
        }
      },
      "page": {
        "description": "Tại đây bạn có thể cấu hình các gói đăng ký, bao gồm thêm, xóa và chỉnh sửa."
      }
    }
  }
}; 