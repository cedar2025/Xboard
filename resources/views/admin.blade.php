<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $title }}</title>
  <script>
    window.settings = {
      base_url: "/",
      title: "{{ $title }}",
      version: "{{ $version }}",
      logo: "{{ $logo }}",
      secure_path: "{{ $secure_path }}",
    };
  </script>
  <script type="module" crossorigin src="/assets/admin/assets/index.js"></script>
  <link rel="stylesheet" crossorigin href="/assets/admin/assets/index.css" />
  <link rel="stylesheet" crossorigin href="/assets/admin/assets/vendor.css">
  <script src="/assets/admin/locales/en-US.js"></script>
  <script src="/assets/admin/locales/zh-CN.js"></script>
  <script src="/assets/admin/locales/ko-KR.js"></script>
  <style>
    .xboard-app-downloads-entry {
      position: fixed;
      right: 18px;
      bottom: 18px;
      z-index: 2147483000;
      display: none;
      align-items: center;
      justify-content: center;
      min-height: 36px;
      border: 1px solid rgba(15, 23, 42, 0.12);
      border-radius: 6px;
      padding: 0 12px;
      color: #fff;
      background: #0f172a;
      box-shadow: 0 10px 24px rgba(15, 23, 42, 0.18);
      font: 500 13px/1.2 Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      text-decoration: none;
    }
    .xboard-app-downloads-entry:hover {
      background: #1e293b;
    }
    .xboard-app-downloads-entry.is-visible {
      display: inline-flex;
    }
    .xboard-admin-ticket-row-click {
      cursor: pointer;
    }
  </style>
</head>

<body>
  <div id="root"></div>
  <a class="xboard-app-downloads-entry" id="xboard-app-downloads-entry" href="/{{ $secure_path }}/app-downloads">App 下载管理</a>
  <script>
    (function () {
      var entry = document.getElementById("xboard-app-downloads-entry");
      if (!entry) return;

      function hasAdminToken() {
        return !!(
          localStorage.getItem("authorization") ||
          localStorage.getItem("XBOARD_ACCESS_TOKEN") ||
          localStorage.getItem("access_token")
        );
      }

      function isAuthPage() {
        return /^#\/?(sign-in|sign-in-2|sign-up|forgot-password|otp|login|register|forget)(?:[/?#]|$)/.test(window.location.hash || "");
      }

      function syncEntryVisibility() {
        entry.classList.toggle("is-visible", hasAdminToken() && !isAuthPage());
      }

      syncEntryVisibility();
      window.addEventListener("storage", syncEntryVisibility);
      window.addEventListener("focus", syncEntryVisibility);
      window.addEventListener("hashchange", syncEntryVisibility);
      window.addEventListener("pageshow", syncEntryVisibility);
      setInterval(syncEntryVisibility, 500);
    })();
  </script>
  <script>
    (function () {
      var VIEW_DETAIL_TITLES = ["查看详情", "View Details", "상세 보기"];

      function isTicketRoute() {
        return /^#\/?user\/ticket(?:[/?#]|$)/.test(window.location.hash || "");
      }

      function isInteractiveTarget(target) {
        if (!(target instanceof Element)) return true;
        return !!target.closest('button, a, input, textarea, select, label, [role="button"], [role="menuitem"], [contenteditable="true"]');
      }

      function findTicketViewButton(row) {
        var buttons = row.querySelectorAll("button[title]");
        for (var index = 0; index < buttons.length; index += 1) {
          if (VIEW_DETAIL_TITLES.indexOf(buttons[index].getAttribute("title")) !== -1) {
            return buttons[index];
          }
        }
        return null;
      }

      function closestTicketRow(target) {
        if (!isTicketRoute() || !(target instanceof Element)) return null;
        var row = target.closest("tbody tr");
        if (!row || !findTicketViewButton(row)) return null;
        return row;
      }

      document.addEventListener("pointerover", function (event) {
        var row = closestTicketRow(event.target);
        if (row) row.classList.add("xboard-admin-ticket-row-click");
      }, true);

      document.addEventListener("click", function (event) {
        if (isInteractiveTarget(event.target)) return;

        var row = closestTicketRow(event.target);
        if (!row) return;

        var viewButton = findTicketViewButton(row);
        if (!viewButton) return;

        event.preventDefault();
        viewButton.click();
      });
    })();
  </script>
  <script>
    (function () {
      var KNOWLEDGE_IMAGE_MAX_SIDE = 2560;
      var KNOWLEDGE_IMAGE_WEBP_QUALITY = 0.88;

      function isKnowledgeRoute() {
        return /^#\/?(?:config\/)?knowledge(?:[\/?#]|$)/.test(window.location.hash || "");
      }

      function getAdminToken() {
        var token = (
          localStorage.getItem("XBOARD_ACCESS_TOKEN") ||
          localStorage.getItem("authorization") ||
          localStorage.getItem("access_token") ||
          ""
        );

        try {
          var parsedToken = JSON.parse(token);
          if (parsedToken && typeof parsedToken.value === "string") {
            return parsedToken.value;
          }
        } catch (error) {
          // Legacy token values are stored as plain strings.
        }

        return token;
      }

      function getEditableTarget(target) {
        if (!(target instanceof Element)) return null;
        if (target.matches("textarea, [contenteditable=\"true\"], [contenteditable=\"plaintext-only\"]")) {
          return target;
        }
        return target.closest("textarea, [contenteditable=\"true\"], [contenteditable=\"plaintext-only\"]");
      }

      function getClipboardImages(event) {
        var items = event.clipboardData && event.clipboardData.items;
        if (!items) return [];

        var files = [];
        for (var index = 0; index < items.length; index += 1) {
          var item = items[index];
          if (item.kind === "file" && /^image\//.test(item.type || "")) {
            var file = item.getAsFile();
            if (file) files.push(file);
          }
        }
        return files;
      }

      function saveSelection(target) {
        if (target instanceof HTMLTextAreaElement) {
          return {
            type: "textarea",
            start: target.selectionStart,
            end: target.selectionEnd
          };
        }

        var selection = window.getSelection && window.getSelection();
        if (selection && selection.rangeCount > 0) {
          return {
            type: "range",
            range: selection.getRangeAt(0).cloneRange()
          };
        }

        return { type: "none" };
      }

      function restoreSelection(target, savedSelection) {
        target.focus();
        if (savedSelection.type === "textarea" && target instanceof HTMLTextAreaElement) {
          target.selectionStart = savedSelection.start;
          target.selectionEnd = savedSelection.end;
          return;
        }

        if (savedSelection.type === "range") {
          var selection = window.getSelection && window.getSelection();
          if (selection) {
            selection.removeAllRanges();
            selection.addRange(savedSelection.range);
          }
        }
      }

      function insertMarkdown(target, savedSelection, markdown) {
        restoreSelection(target, savedSelection);

        if (target instanceof HTMLTextAreaElement) {
          target.setRangeText(markdown, target.selectionStart, target.selectionEnd, "end");
          target.dispatchEvent(new Event("input", { bubbles: true }));
          target.dispatchEvent(new Event("change", { bubbles: true }));
          savedSelection.start = target.selectionStart;
          savedSelection.end = target.selectionEnd;
          return;
        }

        document.execCommand("insertText", false, markdown);
        target.dispatchEvent(new Event("input", { bubbles: true }));
        savedSelection.range = window.getSelection().getRangeAt(0).cloneRange();
      }

      function notifyUploadError(message) {
        window.alert(message || "图片上传失败，请稍后重试");
      }

      function canvasToBlob(canvas, mimeType, quality) {
        return new Promise(function (resolve) {
          canvas.toBlob(resolve, mimeType, quality);
        });
      }

      function compressKnowledgeImage(file) {
        if (!file || !/^image\//.test(file.type || "") || file.type === "image/gif") {
          return Promise.resolve(file);
        }

        if (!window.createImageBitmap || typeof File !== "function") {
          return Promise.resolve(file);
        }

        return createImageBitmap(file).then(function (bitmap) {
          var maxSide = Math.max(bitmap.width, bitmap.height);
          var scale = maxSide > KNOWLEDGE_IMAGE_MAX_SIDE ? KNOWLEDGE_IMAGE_MAX_SIDE / maxSide : 1;
          var width = Math.max(1, Math.round(bitmap.width * scale));
          var height = Math.max(1, Math.round(bitmap.height * scale));
          var canvas = document.createElement("canvas");
          canvas.width = width;
          canvas.height = height;

          var context = canvas.getContext("2d");
          if (!context) {
            if (typeof bitmap.close === "function") bitmap.close();
            return file;
          }

          context.imageSmoothingEnabled = true;
          context.imageSmoothingQuality = "high";
          context.drawImage(bitmap, 0, 0, width, height);
          if (typeof bitmap.close === "function") bitmap.close();

          return canvasToBlob(canvas, "image/webp", KNOWLEDGE_IMAGE_WEBP_QUALITY).then(function (blob) {
            if (!blob || blob.size >= file.size) return file;

            var baseName = file.name ? file.name.replace(/\.[^.]+$/, "") : "image";
            return new File([blob], baseName + ".webp", {
              type: "image/webp",
              lastModified: Date.now()
            });
          });
        }).catch(function () {
          return file;
        });
      }

      function uploadKnowledgeImage(file) {
        return compressKnowledgeImage(file).then(function (uploadFile) {
          var formData = new FormData();
          formData.append("file", uploadFile);

          return fetch("/api/v2/" + window.settings.secure_path + "/knowledge/upload-image", {
            method: "POST",
            headers: {
              Authorization: getAdminToken()
            },
            body: formData
          }).then(function (response) {
            return response.json().then(function (result) {
              if (!response.ok || !result || !result.data || !result.data.url) {
                throw new Error((result && result.message) || "图片上传失败");
              }
              return result.data.url;
            });
          });
        });
      }

      document.addEventListener("paste", function (event) {
        if (!isKnowledgeRoute()) return;

        var target = getEditableTarget(event.target);
        if (!target) return;

        var files = getClipboardImages(event);
        if (files.length === 0) return;

        event.preventDefault();
        var savedSelection = saveSelection(target);

        files.reduce(function (chain, file, index) {
          return chain.then(function () {
            return uploadKnowledgeImage(file).then(function (url) {
              var altText = file.name ? file.name.replace(/\.[^.]+$/, "") : "image";
              var markdown = `![${altText}](${url})` + (index < files.length - 1 ? "\n" : "");
              insertMarkdown(target, savedSelection, markdown);
            });
          });
        }, Promise.resolve()).catch(function (error) {
          notifyUploadError(error && error.message);
        });
      }, true);
    })();
  </script>
</body>

</html>
