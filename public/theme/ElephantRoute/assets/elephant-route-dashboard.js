(function () {
  var DOWNLOAD_URL = '/download/index.html';
  var SUBSCRIBE_API_PATH = '/api/v1/user/getSubscribe';
  var TRAFFIC_PACKAGE_API_PATH = '/api/v1/user/traffic-package/fetch';
  var ORDER_SAVE_API_PATH = '/api/v1/user/order/save';
  var SERVER_API_PATH = '/api/v1/user/server/fetch';
  var DIFY_CONTEXT_API_PATH = '/api/v1/user/support/dify-context';
  var DIFY_OPEN_QUERY_KEY = 'open_ai_support';
  var KARING_ICON_PATH = 'images/karing.png';
  var CLASH_MI_ICON_PATH = 'images/clash-mi.png';
  var CLASH_VERGE_ICON_PATH = 'images/clash-verge.png';
  var ACCESS_TOKEN_STORAGE_KEY = 'VUE_NAIVE_ACCESS_TOKEN';
  var THEME_PALETTES = {
    default: { primary: '#316C72', hover: '#316C72', pressed: '#2B4C59' },
    blue: { primary: '#0665d0', hover: '#2a84de', pressed: '#004085' },
    black: { primary: '#343a40', hover: '#23272b', pressed: '#1d2124' },
    darkblue: { primary: '#004175', hover: '#002c4c', pressed: '#001f35' },
    titianred: { primary: '#D34947', hover: '#DC6A68', pressed: '#A92E2D' },
    kleinblue: { primary: '#002FA7', hover: '#1F55C8', pressed: '#001F73' },
    chinared: { primary: '#C8161D', hover: '#D93940', pressed: '#930F15' },
    hermesorange: { primary: '#EB5C20', hover: '#F07A49', pressed: '#B94112' },
    marsgreen: { primary: '#018B8D', hover: '#1BA6A8', pressed: '#006668' }
  };
  var PROBLEM_APPEAL_LABEL = '提交问题申诉';
  var PROBLEM_APPEAL_DESCRIPTION = '提交问题后将在3小时内回复';
  var PROBLEM_APPEAL_TITLE_COLOR = THEME_PALETTES.chinared.primary;
  var HIDDEN_SIDEBAR_MENU_LABELS = ['流量明细'];
  var TICKET_APPEAL_TEXT_REPLACEMENTS = [
    ['我的工单', '问题申诉'],
    ['工单历史', '申诉记录'],
    ['新的工单', '提交申诉'],
    ['请输入工单主题', '请输入申诉主题'],
    ['工单级别', '申诉级别'],
    ['工单等级', '申诉级别'],
    ['请选择工单优先级', '请选择申诉优先级'],
    ['请选择工单等级', '请选择申诉优先级'],
    ['请描述您遇到的问题', '请描述您的申诉问题'],
    ['请描述你遇到的问题', '请描述您的申诉问题'],
    ['工单详情', '申诉详情'],
    ['工单状态', '申诉状态']
  ];
  var TICKET_APPEAL_EXACT_TEXT_REPLACEMENTS = [
    ['主题', '申诉主题'],
    ['消息', '申诉内容']
  ];
  var DASHBOARD_ROUTES = ['/', '/dashboard', '/home', '/index'];
  var AUTH_ROUTES = ['/sign-in', '/sign-up', '/login', '/register', '/forgetpassword', '/forgot-password'];
  var maxAttempts = 80;
  var attempts = 0;
  var retryTimer = null;
  var nodeCompatibilityPromise = null;
  var difySupportPromise = null;
  var difyAutoOpenHandled = false;
  var subscribeInfoPromise = null;
  var subscribeInfoCachedAt = 0;
  var trafficPackagesPromise = null;
  var trafficPackagesCachedAt = 0;

  function getRoute() {
    var hash = window.location.hash || '';
    var route = hash.replace(/^#/, '').split('?')[0] || '/';
    return route.replace(/\/$/, '') || '/';
  }

  function isAuthRoute(route) {
    return AUTH_ROUTES.some(function (item) {
      return route === item || route.indexOf(item + '/') === 0;
    });
  }

  function hasAuthForm() {
    var passwordInput = document.querySelector('input[type="password"], input[placeholder*="密码"]');
    var authLink = document.querySelector('a[href="#/register"], a[href="#/login"], a[href="#/forgetpassword"], a[href="#/forgot-password"], a[href="#/sign-up"], a[href="#/sign-in"]');
    return Boolean(passwordInput && authLink && !document.querySelector('.n-layout-sider'));
  }

  function isDashboardRoute() {
    var route = getRoute();
    if (isAuthRoute(route) || hasAuthForm()) return false;
    return DASHBOARD_ROUTES.indexOf(route) !== -1 || route.indexOf('/dashboard/') === 0;
  }

  function getDashboardThemePalette() {
    var color = window.settings && window.settings.theme ? window.settings.theme.color : 'default';
    return Object.prototype.hasOwnProperty.call(THEME_PALETTES, color) ? THEME_PALETTES[color] : THEME_PALETTES.default;
  }

  function hexToRgb(color) {
    var normalized = String(color || '').replace('#', '').slice(0, 6);
    if (!/^[0-9a-fA-F]{6}$/.test(normalized)) return { r: 49, g: 108, b: 114 };
    return {
      r: parseInt(normalized.slice(0, 2), 16),
      g: parseInt(normalized.slice(2, 4), 16),
      b: parseInt(normalized.slice(4, 6), 16)
    };
  }

  function rgbaFromHex(color, alpha) {
    var rgb = hexToRgb(color);
    return 'rgba(' + rgb.r + ', ' + rgb.g + ', ' + rgb.b + ', ' + alpha + ')';
  }

  function applyDashboardThemeTokens() {
    var palette = getDashboardThemePalette();
    var root = document.documentElement;
    if (!root || !root.style) return;

    root.style.setProperty('--er-subscribe-action-primary', palette.primary);
    root.style.setProperty('--er-subscribe-action-primary-hover', palette.hover);
    root.style.setProperty('--er-subscribe-action-primary-pressed', palette.pressed);
    root.style.setProperty('--er-subscribe-action-bg', rgbaFromHex(palette.primary, 0.08));
    root.style.setProperty('--er-subscribe-action-bg-hover', rgbaFromHex(palette.primary, 0.13));
    root.style.setProperty('--er-subscribe-action-border', rgbaFromHex(palette.primary, 0.18));
    root.style.setProperty('--er-subscribe-action-border-hover', rgbaFromHex(palette.primary, 0.3));
    root.style.setProperty('--er-subscribe-action-focus', rgbaFromHex(palette.primary, 0.22));
  }

  function removeDownloadEntry() {
    var existing = document.getElementById('er-dashboard-download');
    if (existing) existing.remove();
  }

  function removeSubscribeActions() {
    var existing = document.getElementById('er-subscribe-action-panel');
    if (existing) existing.remove();

    document.querySelectorAll('.er-subscribe-layout').forEach(function (layout) {
      var main = layout.querySelector('.er-subscribe-main');
      if (main) {
        while (main.firstChild) {
          layout.parentElement.insertBefore(main.firstChild, layout);
        }
      }
      layout.remove();
    });
  }

  function findTextElement(text) {
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
      acceptNode: function (node) {
        if (!node.nodeValue || node.nodeValue.indexOf(text) === -1) return NodeFilter.FILTER_REJECT;
        if (node.parentElement && node.parentElement.closest('#er-dashboard-download')) return NodeFilter.FILTER_REJECT;
        if (node.parentElement && node.parentElement.closest('#er-subscribe-action-panel')) return NodeFilter.FILTER_REJECT;
        return NodeFilter.FILTER_ACCEPT;
      }
    });

    var node = walker.nextNode();
    return node ? node.parentElement : null;
  }

  function findTextElements(text) {
    var matches = [];
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
      acceptNode: function (node) {
        if (!node.nodeValue || node.nodeValue.indexOf(text) === -1) return NodeFilter.FILTER_REJECT;
        if (node.parentElement && node.parentElement.closest('#er-dashboard-download')) return NodeFilter.FILTER_REJECT;
        if (node.parentElement && node.parentElement.closest('#er-subscribe-action-panel')) return NodeFilter.FILTER_REJECT;
        return NodeFilter.FILTER_ACCEPT;
      }
    });

    var node;
    while ((node = walker.nextNode())) {
      if (node.parentElement) matches.push(node.parentElement);
    }
    return matches;
  }

  function closestShortcut(element) {
    var node = element;
    while (node && node !== document.body) {
      if (node.id === 'er-dashboard-download') return null;
      if (node.matches('a, button, [role="button"], .cursor-pointer, .n-list-item, .n-card, .v2board-shortcuts-item, [class*="shortcut"], [class*="Shortcut"]')) {
        return node;
      }
      node = node.parentElement;
    }
    return element;
  }

  function closestCard(element) {
    var node = element;
    while (node && node !== document.body) {
      if (node.classList && node.classList.contains('n-card')) return node;
      node = node.parentElement;
    }
    return null;
  }

  function getNodeText(node) {
    return (node && node.textContent ? node.textContent : '').replace(/\s+/g, ' ').trim();
  }

  function isDesktopPlatform() {
    var userAgent = navigator.userAgent || '';
    var platform = navigator.platform || '';
    if (/Android/i.test(userAgent)) return false;

    var isTouchAppleDevice = /iPhone|iPad|iPod/i.test(userAgent) || (platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    if (isTouchAppleDevice) return false;

    var isMacLike = /Macintosh|Mac OS X/i.test(userAgent) || /Mac/i.test(platform);
    var isWindowsLike = /Windows/i.test(userAgent) || /Win/i.test(platform);
    return isMacLike || isWindowsLike;
  }

  function isAppleMobilePlatform() {
    var userAgent = navigator.userAgent || '';
    var platform = navigator.platform || '';
    if (/Android/i.test(userAgent)) return false;
    return /iPhone|iPad|iPod/i.test(userAgent) || (platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  }

  function replaceAppealText(value) {
    if (!value) return value;
    var next = value;
    TICKET_APPEAL_TEXT_REPLACEMENTS.forEach(function (pair) {
      next = next.split(pair[0]).join(pair[1]);
    });
    return replaceExactAppealText(next);
  }

  function replaceExactAppealText(value) {
    var normalized = value.trim();
    if (!normalized) return value;

    for (var i = 0; i < TICKET_APPEAL_EXACT_TEXT_REPLACEMENTS.length; i += 1) {
      var pair = TICKET_APPEAL_EXACT_TEXT_REPLACEMENTS[i];
      if (normalized === pair[0]) {
        var leading = value.match(/^\s*/)[0];
        var trailing = value.match(/\s*$/)[0];
        return leading + pair[1] + trailing;
      }
    }

    return value;
  }

  function replaceAppealTextInNode(root) {
    if (!root) return;

    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode: function (node) {
        var parent = node.parentElement;
        if (!node.nodeValue || !parent) return NodeFilter.FILTER_REJECT;
        if (parent.closest('script, style, textarea, input, select')) return NodeFilter.FILTER_REJECT;
        return NodeFilter.FILTER_ACCEPT;
      }
    });

    var node;
    while ((node = walker.nextNode())) {
      var next = replaceAppealText(node.nodeValue);
      if (next !== node.nodeValue) node.nodeValue = next;
    }
  }

  function replaceAppealTextAttributes(root) {
    if (!root || !root.querySelectorAll) return;

    root.querySelectorAll('[placeholder], [aria-label], [title]').forEach(function (node) {
      ['placeholder', 'aria-label', 'title'].forEach(function (attribute) {
        if (!node.hasAttribute(attribute)) return;
        var value = node.getAttribute(attribute);
        var next = replaceAppealText(value);
        if (next !== value) node.setAttribute(attribute, next);
      });
    });
  }

  function normalizeTicketAppealWording() {
    if (!document.body) return;

    replaceAppealTextInNode(document.body);
    replaceAppealTextAttributes(document.body);
    var nextTitle = replaceAppealText(document.title);
    if (nextTitle !== document.title) document.title = nextTitle;
  }

  function closestSidebarMenuItem(element) {
    if (!element || !element.closest || !element.closest('.n-layout-sider')) return null;
    return element.closest('[role="menuitem"], .n-menu-item, a, button, .cursor-pointer');
  }

  function removeHiddenSidebarMenuItems() {
    HIDDEN_SIDEBAR_MENU_LABELS.forEach(function (label) {
      findTextElements(label).forEach(function (labelElement) {
        var item = closestSidebarMenuItem(labelElement);
        if (item) item.remove();
      });
    });
  }

  function getStoredAccessToken() {
    var raw = window.localStorage ? window.localStorage.getItem(ACCESS_TOKEN_STORAGE_KEY) : null;
    if (!raw) return '';

    try {
      var parsed = JSON.parse(raw);
      if (!parsed || !parsed.value) return '';
      if (parsed.expire && parsed.expire <= Date.now()) return '';
      return parsed.value;
    } catch (error) {
      return '';
    }
  }

  function notify(type, message) {
    if (window.$message && typeof window.$message[type] === 'function') {
      window.$message[type](message);
      return;
    }
    if (type === 'error') console.error(message);
  }

  function getSupportAccessToken() {
    var keys = [ACCESS_TOKEN_STORAGE_KEY, 'XBOARD_ACCESS_TOKEN', 'authorization', 'access_token'];
    for (var i = 0; i < keys.length; i += 1) {
      var raw = window.localStorage ? window.localStorage.getItem(keys[i]) : null;
      if (!raw) continue;

      try {
        var parsed = JSON.parse(raw);
        if (parsed && parsed.value && (!parsed.expire || parsed.expire > Date.now())) {
          return parsed.value;
        }
      } catch (error) {
        return raw;
      }
    }

    return '';
  }

  function injectDifySupportStyle() {
    if (document.getElementById('er-dify-support-style')) return;

    var style = document.createElement('style');
    style.id = 'er-dify-support-style';
    style.textContent = [
      '#dify-chatbot-bubble-button{background-color:#1C64F2!important;left:1rem!important;right:auto!important;}',
      '#dify-chatbot-bubble-window{width:24rem!important;height:40rem!important;left:1rem!important;right:auto!important;}',
      '@media (max-width:640px){#dify-chatbot-bubble-window{width:calc(100vw - 2rem)!important;height:calc(100vh - 8rem)!important;left:1rem!important;right:auto!important;}}'
    ].join('');
    document.head.appendChild(style);
  }

  function loadDifySupportScript(src, id) {
    return new Promise(function (resolve, reject) {
      if (document.getElementById(id)) {
        resolve();
        return;
      }

      var script = document.createElement('script');
      script.src = src;
      script.id = id;
      script.defer = true;
      script.onload = resolve;
      script.onerror = function () {
        reject(new Error('AI 客服脚本加载失败'));
      };
      document.body.appendChild(script);
    });
  }

  function waitForDifyBubbleButton(timeoutMs) {
    var deadline = Date.now() + (timeoutMs || 8000);

    return new Promise(function (resolve, reject) {
      function check() {
        var button = document.getElementById('dify-chatbot-bubble-button');
        if (button) {
          resolve(button);
          return;
        }

        if (Date.now() > deadline) {
          reject(new Error('AI 客服按钮加载超时'));
          return;
        }

        window.setTimeout(check, 100);
      }

      check();
    });
  }

  function fetchDifySupportContext() {
    var token = getSupportAccessToken();
    if (!token) return Promise.reject(new Error('请先登录后再使用 AI 客服'));

    return fetch(DIFY_CONTEXT_API_PATH + '?t=' + Date.now(), {
      method: 'GET',
      headers: {
        Authorization: token,
        'Content-Language': document.documentElement.lang || 'zh-CN'
      },
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json();
    }).then(function (payload) {
      if (!payload || payload.status !== 'success' || !payload.data) {
        throw new Error(payload && payload.message ? payload.message : 'AI 客服配置读取失败');
      }

      return payload.data;
    });
  }

  function bootDifySupportWidget(context) {
    injectDifySupportStyle();
    window.difyChatbotConfig = {
      token: context.token,
      baseUrl: context.base_url,
      dynamicScript: true,
      systemVariables: {
        user_id: String(context.user_id)
      },
      userVariables: {
        name: context.user_display_name
      }
    };

    return loadDifySupportScript(context.embed_script_url, context.token);
  }

  function ensureDifySupportWidget() {
    if (difySupportPromise) return difySupportPromise;

    difySupportPromise = fetchDifySupportContext()
      .then(bootDifySupportWidget)
      .then(function () {
        return waitForDifyBubbleButton(8000);
      })
      .catch(function (error) {
        difySupportPromise = null;
        throw error;
      });

    return difySupportPromise;
  }

  function isDifyChatWindowOpen() {
    var chatWindow = document.getElementById('dify-chatbot-bubble-window');
    if (!chatWindow) return false;

    var style = window.getComputedStyle(chatWindow);
    return style.display !== 'none' && style.visibility !== 'hidden' && chatWindow.offsetWidth > 0 && chatWindow.offsetHeight > 0;
  }

  function openDifySupportChat() {
    return ensureDifySupportWidget().then(function (button) {
      if (!isDifyChatWindowOpen()) button.click();
      return true;
    }).catch(function (error) {
      console.error('打开 AI 客服失败:', error);
      notify('error', error.message || 'AI 客服加载失败');
      return false;
    });
  }

  function shouldPreloadDifySupportBubble() {
    var route = getRoute();
    return !isAuthRoute(route) && !hasAuthForm() && Boolean(getSupportAccessToken());
  }

  function preloadDifySupportBubble() {
    if (!shouldPreloadDifySupportBubble()) return;

    ensureDifySupportWidget().catch(function (error) {
      console.warn('AI 客服气泡加载失败:', error);
    });
  }

  function shouldAutoOpenDifySupport() {
    try {
      return new URLSearchParams(window.location.search).get(DIFY_OPEN_QUERY_KEY) === '1';
    } catch (error) {
      return false;
    }
  }

  function consumeDifySupportOpenFlag() {
    if (!window.history || typeof window.history.replaceState !== 'function') return;

    try {
      var url = new URL(window.location.href);
      url.searchParams.delete(DIFY_OPEN_QUERY_KEY);
      window.history.replaceState({}, document.title, url.pathname + url.search + url.hash);
    } catch (error) {
      // Keep the compatibility URL intact if the browser cannot rewrite it.
    }
  }

  function maybeAutoOpenDifySupport() {
    if (difyAutoOpenHandled || !shouldAutoOpenDifySupport()) return;

    difyAutoOpenHandled = true;
    consumeDifySupportOpenFlag();
    openDifySupportChat();
  }

  function copyTextWithExecCommand(text) {
    return new Promise(function (resolve, reject) {
      var textarea = document.createElement('textarea');
      textarea.value = text;
      textarea.setAttribute('readonly', '');
      textarea.style.position = 'fixed';
      textarea.style.left = '-9999px';
      textarea.style.top = '0';
      document.body.appendChild(textarea);
      textarea.select();

      try {
        if (document.execCommand('copy')) {
          resolve();
        } else {
          reject(new Error('copy command failed'));
        }
      } catch (error) {
        reject(error);
      } finally {
        textarea.remove();
      }
    });
  }

  function copyText(text) {
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
      return navigator.clipboard.writeText(text).catch(function () {
        return copyTextWithExecCommand(text);
      });
    }

    return copyTextWithExecCommand(text);
  }

  function requestAuthenticatedJson(path, token, options) {
    options = options || {};
    var method = options.method || 'GET';
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      var url = method === 'GET' ? path + '?t=' + Date.now() : path;
      xhr.open(method, url, true);
      xhr.setRequestHeader('Authorization', token);
      xhr.setRequestHeader('Content-Language', document.documentElement.lang || 'zh-CN');
      if (options.body) {
        xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
      }
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;

        var payload = null;
        try {
          payload = JSON.parse(xhr.responseText || '{}');
        } catch (error) {
          reject(error);
          return;
        }

        if (xhr.status >= 200 && xhr.status < 300) {
          resolve(payload);
          return;
        }

        reject(new Error('request failed: ' + xhr.status));
      };
      xhr.onerror = function () {
        reject(new Error('network error'));
      };
      xhr.send(options.body ? JSON.stringify(options.body) : null);
    });
  }

  function fetchSubscribeInfo() {
    if (subscribeInfoPromise && Date.now() - subscribeInfoCachedAt < 3000) {
      return subscribeInfoPromise;
    }

    var token = getSupportAccessToken();
    if (!token) {
      console.warn('订阅信息加载跳过: 未找到认证信息');
      return Promise.resolve(null);
    }

    subscribeInfoCachedAt = Date.now();
    subscribeInfoPromise = requestAuthenticatedJson(SUBSCRIBE_API_PATH, token).then(function (payload) {
      var data = payload && payload.data ? payload.data : null;
      if (!data) return null;
      var subscribeUrl = data && data.subscribe_url;
      return Object.assign({}, data, {
        subscribeUrl: subscribeUrl || '',
        title: window.settings && window.settings.title ? window.settings.title : document.title || '订阅'
      });
    }).catch(function (error) {
      subscribeInfoPromise = null;
      throw error;
    });

    return subscribeInfoPromise;
  }

  function fetchTrafficPackages() {
    if (trafficPackagesPromise && Date.now() - trafficPackagesCachedAt < 3000) {
      return trafficPackagesPromise;
    }

    var token = getSupportAccessToken();
    if (!token) {
      console.warn('流量包信息加载跳过: 未找到认证信息');
      return Promise.resolve([]);
    }

    trafficPackagesCachedAt = Date.now();
    trafficPackagesPromise = requestAuthenticatedJson(TRAFFIC_PACKAGE_API_PATH, token).then(function (payload) {
      var data = payload && payload.data ? payload.data : [];
      return Array.isArray(data) ? data : [];
    }).catch(function (error) {
      trafficPackagesPromise = null;
      throw error;
    });

    return trafficPackagesPromise;
  }

  function createTrafficPackageOrder(packageId) {
    var token = getSupportAccessToken();
    if (!token) {
      return Promise.reject(new Error('missing token'));
    }

    return requestAuthenticatedJson(ORDER_SAVE_API_PATH, token, {
      method: 'POST',
      body: { traffic_package_id: packageId }
    }).then(function (payload) {
      if (!payload || !payload.data) {
        throw new Error('missing trade no');
      }
      return payload.data;
    });
  }

  function copySubscribeUrl() {
    return fetchSubscribeInfo().then(function (info) {
      if (!info) {
        notify('error', '未登录');
        return false;
      }
      if (!info.subscribeUrl) {
        notify('error', '订阅链接不存在');
        return false;
      }
      return copyText(info.subscribeUrl).then(function () {
        return true;
      });
    }).then(function (copied) {
      if (!copied) return false;
      notify('success', '复制成功');
      return true;
    }).catch(function (error) {
      console.error('复制订阅链接失败:', error);
      notify('error', '复制失败');
      return false;
    });
  }

  function getThemeAssetUrl(path) {
    var base = window.settings && window.settings.assets_path ? window.settings.assets_path : '/theme/ElephantRoute/assets';
    return base.replace(/\/$/, '') + '/' + path;
  }

  function buildKaringImportUrl(subscribeUrl, title) {
    return 'karing://install-config?url=' + encodeURIComponent(subscribeUrl) + '&name=' + encodeURIComponent(title || '订阅');
  }

  function buildClashMiImportUrl(subscribeUrl) {
    return 'clashmi://install-config?url=' + encodeURIComponent(subscribeUrl);
  }

  function openKaringImport() {
    return fetchSubscribeInfo().then(function (info) {
      if (!info) {
        notify('error', '未登录');
        return false;
      }
      if (!info.subscribeUrl) {
        notify('error', '订阅链接不存在');
        return false;
      }
      window.location.href = buildKaringImportUrl(info.subscribeUrl, info.title);
      return true;
    }).catch(function (error) {
      console.error('打开 Karing 导入失败:', error);
      notify('error', 'Karing 导入失败');
      return false;
    });
  }

  function openClashMiImport() {
    return fetchSubscribeInfo().then(function (info) {
      if (!info) {
        notify('error', '未登录');
        return false;
      }
      if (!info.subscribeUrl) {
        notify('error', '订阅链接不存在');
        return false;
      }
      window.location.href = buildClashMiImportUrl(info.subscribeUrl);
      return true;
    }).catch(function (error) {
      console.error('打开 Clash Mi 导入失败:', error);
      notify('error', 'Clash Mi 导入失败');
      return false;
    });
  }

  function loadNodeCompatibility() {
    if (nodeCompatibilityPromise) return nodeCompatibilityPromise;

    var token = getStoredAccessToken();
    if (!token) return Promise.resolve(null);

    nodeCompatibilityPromise = fetch(SERVER_API_PATH + '?t=' + Date.now(), {
      method: 'GET',
      headers: {
        Authorization: token,
        'Content-Language': document.documentElement.lang || 'zh-CN'
      },
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json();
    }).then(function (payload) {
      var nodes = payload && payload.data && Array.isArray(payload.data) ? payload.data : [];
      var counts = nodes.reduce(function (memo, node) {
        var type = String(node && node.type || '').toLowerCase();
        if (!type) return memo;
        memo.total += 1;
        if (type === 'vless') memo.vless += 1;
        if (['shadowsocks', 'vmess', 'trojan', 'hysteria'].indexOf(type) !== -1) {
          memo.surgeCompatible += 1;
        }
        return memo;
      }, {
        total: 0,
        vless: 0,
        surgeCompatible: 0
      });

      return counts.total > 0 && counts.vless > 0 && counts.surgeCompatible === 0;
    }).catch(function (error) {
      console.warn('读取节点兼容性失败:', error);
      nodeCompatibilityPromise = null;
      return null;
    });

    return nodeCompatibilityPromise;
  }

  function findSubscribeMenuItem(text) {
    var targetText = findTextElement(text);
    if (!targetText) return null;

    return targetText.closest('button, a, [role="button"], .cursor-pointer, .n-list-item') || closestShortcut(targetText);
  }

  function findSubscribeListItem(text) {
    var targetText = findTextElement(text);
    if (!targetText) return null;

    return targetText.closest('.n-list-item, li, [role="listitem"]') || findSubscribeMenuItem(text);
  }

  function hideUnsupportedSurgeOption() {
    var surgeItem = findSubscribeMenuItem('Surge');
    if (!surgeItem || surgeItem.dataset.erSurgeHidden === '1') return;

    loadNodeCompatibility().then(function (shouldHide) {
      if (!shouldHide || !document.body.contains(surgeItem) || surgeItem.dataset.erSurgeHidden === '1') return;

      surgeItem.dataset.erSurgeHidden = '1';
      surgeItem.setAttribute('data-er-surge-hidden', '1');
      surgeItem.setAttribute('hidden', '');
      surgeItem.style.display = 'none';
    });
  }

  function replaceKaringIcon(root) {
    var icon = root.querySelector('img');
    if (!icon) {
      icon = document.createElement('img');
      var svg = root.querySelector('svg');
      if (svg && svg.parentElement) {
        svg.replaceWith(icon);
      } else {
        root.insertBefore(icon, root.firstChild);
      }
    }

    icon.src = getThemeAssetUrl(KARING_ICON_PATH);
    icon.alt = 'Karing';
    icon.classList.add('er-karing-subscribe-icon');
  }

  function replaceClashMiIcon(root) {
    var icon = root.querySelector('img');
    if (!icon) {
      icon = document.createElement('img');
      var svg = root.querySelector('svg');
      if (svg && svg.parentElement) {
        svg.replaceWith(icon);
      } else {
        root.insertBefore(icon, root.firstChild);
      }
    }

    icon.src = getThemeAssetUrl(CLASH_MI_ICON_PATH);
    icon.alt = 'Clash Mi';
    icon.classList.add('er-clash-mi-subscribe-icon');
  }

  function replaceClashVergeIcon(root) {
    var icon = root.querySelector('img');
    if (!icon) {
      icon = document.createElement('img');
      var svg = root.querySelector('svg');
      if (svg && svg.parentElement) {
        svg.replaceWith(icon);
      } else {
        root.insertBefore(icon, root.firstChild);
      }
    }

    icon.src = getThemeAssetUrl(CLASH_VERGE_ICON_PATH);
    icon.alt = 'Clash Verge';
    icon.classList.add('er-clash-verge-subscribe-icon');
  }

  function createKaringSubscribeItem(hiddifyItem) {
    var karingItem = hiddifyItem.cloneNode(true);
    karingItem.dataset.erKaringSubscribeItem = '1';
    karingItem.setAttribute('aria-label', '导入到 Karing');
    karingItem.removeAttribute('title');
    replaceText(karingItem, 'Hiddify', 'Karing');
    replaceKaringIcon(karingItem);

    if (karingItem.tagName && karingItem.tagName.toLowerCase() === 'a') {
      karingItem.href = '#';
      karingItem.setAttribute('href', '#');
    }

    karingItem.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      openKaringImport();
    });

    return karingItem;
  }

  function enhanceKaringSubscribeOption() {
    var hiddifyItem = findSubscribeListItem('Hiddify');
    if (!hiddifyItem) return false;

    var container = hiddifyItem.parentElement || hiddifyItem;
    if (container.querySelector('[data-er-karing-subscribe-item="1"]')) return true;

    var karingItem = createKaringSubscribeItem(hiddifyItem);
    hiddifyItem.insertAdjacentElement('afterend', karingItem);
    return true;
  }

  function createClashMiSubscribeItem(copyItem) {
    var clashMiItem = copyItem.cloneNode(true);
    clashMiItem.dataset.erClashMiSubscribeItem = '1';
    clashMiItem.setAttribute('aria-label', '导入到 Clash Mi');
    clashMiItem.removeAttribute('title');
    replaceText(clashMiItem, getNodeText(clashMiItem), '导入到 Clash Mi');
    if (getNodeText(clashMiItem).indexOf('导入到 Clash Mi') === -1) {
      replaceText(clashMiItem, '复制订阅地址', '导入到 Clash Mi');
      replaceText(clashMiItem, '复制订阅链接', '导入到 Clash Mi');
    }
    replaceClashMiIcon(clashMiItem);

    if (clashMiItem.tagName && clashMiItem.tagName.toLowerCase() === 'a') {
      clashMiItem.href = '#';
      clashMiItem.setAttribute('href', '#');
    }

    clashMiItem.addEventListener('click', function (event) {
      event.preventDefault();
      event.stopPropagation();
      openClashMiImport();
    });

    return clashMiItem;
  }

  function enhanceClashMiSubscribeOption() {
    if (!isAppleMobilePlatform()) return false;

    var copyItem = findSubscribeListItem('复制订阅地址') || findSubscribeListItem('复制订阅链接');
    if (!copyItem) return false;

    var container = copyItem.parentElement || copyItem;
    if (container.querySelector('[data-er-clash-mi-subscribe-item="1"]')) return true;

    var clashMiItem = createClashMiSubscribeItem(copyItem);
    copyItem.insertAdjacentElement('afterend', clashMiItem);
    return true;
  }

  function enhanceDesktopClashVergeSubscribeOption() {
    if (!isDesktopPlatform()) return false;

    var clashItem = findSubscribeListItem('Clash Meta') || findSubscribeListItem('Clash');
    if (!clashItem) return false;

    clashItem.setAttribute('aria-label', '导入到 Clash Verge');
    clashItem.removeAttribute('title');

    var text = getNodeText(clashItem);
    if (text.indexOf('Clash Verge') === -1) {
      if (text.indexOf('Clash Meta') !== -1) {
        replaceText(clashItem, 'Clash Meta', 'Clash Verge');
      } else {
        replaceText(clashItem, 'Clash', 'Clash Verge');
      }
    }

    replaceClashVergeIcon(clashItem);
    return true;
  }

  function findSubscribeShortcut() {
    var subscribeText = findTextElement('一键订阅');
    return subscribeText ? closestShortcut(subscribeText) : null;
  }

  function cleanupDirectQrState() {
    document.body.classList.remove('er-subscribe-direct-qr-active');
    document.querySelectorAll('.er-subscribe-source-menu-hidden, .er-subscribe-source-modal-hidden').forEach(function (node) {
      node.classList.remove('er-subscribe-source-menu-hidden');
      node.classList.remove('er-subscribe-source-modal-hidden');
    });
  }

  function scheduleDirectQrCleanup() {
    var checks = 0;
    var cleanupTimer = window.setInterval(function () {
      checks += 1;
      var hasQrPanel = document.body.textContent.indexOf('选择协议') !== -1 &&
        document.body.textContent.indexOf('使用支持扫码的客户端进行订阅') !== -1;
      if (!hasQrPanel || checks >= 120) {
        window.clearInterval(cleanupTimer);
        cleanupDirectQrState();
      }
    }, 500);
  }

  function hideSourceSubscribeMenu(item) {
    var card = item && item.closest ? item.closest('.n-card') : null;
    var modal = item && item.closest ? item.closest('.n-modal-container') : null;
    var target = modal || card;
    if (!target) return;

    var mask = modal ? modal.querySelector('.n-modal-mask') : null;
    if (mask && document.body.contains(mask)) {
      mask.dispatchEvent(new MouseEvent('click', {
        bubbles: true,
        cancelable: true,
        view: window
      }));
    }

    target.classList.add(modal ? 'er-subscribe-source-modal-hidden' : 'er-subscribe-source-menu-hidden');
  }

  function clickMenuItemByText(text, options) {
    var targetText = findTextElement(text);
    if (!targetText) return false;

    var item = targetText.closest('button, a, [role="button"], .cursor-pointer, .n-list-item') || closestShortcut(targetText);
    if (!item) return false;
    item.click();
    if (options && options.hideSourceMenu) hideSourceSubscribeMenu(item);
    return true;
  }

  function triggerSubscribeMenuItem(itemText, missingMessage, options) {
    var shortcut = findSubscribeShortcut();
    if (!shortcut) {
      notify('error', '订阅入口不可用');
      return false;
    }

    if (options && options.directQr) document.body.classList.add('er-subscribe-direct-qr-active');
    shortcut.click();
    var tries = 0;
    var timer = window.setInterval(function () {
      tries += 1;
      if (clickMenuItemByText(itemText, options) || tries >= 20) {
        window.clearInterval(timer);
        if (tries >= 20) {
          cleanupDirectQrState();
          notify('error', missingMessage);
        } else if (options && options.directQr) {
          scheduleDirectQrCleanup();
        }
      }
    }, 100);
    return true;
  }

  function openSubscribeQrCode() {
    triggerSubscribeMenuItem('扫描二维码订阅', '二维码入口不可用', {
      directQr: true,
      hideSourceMenu: true
    });
  }

  function openDownloadClient() {
    window.open(DOWNLOAD_URL, '_blank', 'noopener,noreferrer');
  }

  function findSubscribeCard() {
    var title = findTextElement('我的订阅');
    if (!title) return null;
    return closestCard(title);
  }

  function shouldEnhanceSubscribeCard(content) {
    var text = getNodeText(content);
    return Boolean(text && (
      (text.indexOf('已用') !== -1 && text.indexOf('总计') !== -1)
      || text.indexOf('购买订阅') !== -1
    ));
  }

  function createSubscribeActionButton(type, label, iconSvg) {
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'er-subscribe-action-button er-subscribe-action-button-' + type;
    button.setAttribute('aria-label', label);
    button.innerHTML = iconSvg + '<span>' + label + '</span>';
    return button;
  }

  function createSubscribeActionPanel() {
    applyDashboardThemeTokens();

    var panel = document.createElement('div');
    panel.id = 'er-subscribe-action-panel';
    panel.className = 'er-subscribe-action-panel';

    var qrIcon = [
      '<svg viewBox="0 0 24 24" aria-hidden="true">',
      '<path d="M4 4h6v6H4z"></path>',
      '<path d="M14 4h6v6h-6z"></path>',
      '<path d="M4 14h6v6H4z"></path>',
      '<path d="M14 14h2v2h-2z"></path>',
      '<path d="M18 14h2v6h-4v-2h2z"></path>',
      '<path d="M14 18h2v2h-2z"></path>',
      '</svg>'
    ].join('');
    var copyIcon = [
      '<svg viewBox="0 0 24 24" aria-hidden="true">',
      '<path d="M8 8h11v11H8z"></path>',
      '<path d="M5 16H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v1"></path>',
      '</svg>'
    ].join('');
    var downloadIcon = [
      '<svg viewBox="0 0 24 24" aria-hidden="true">',
      '<path d="M12 3v11"></path>',
      '<path d="m7 10 5 5 5-5"></path>',
      '<path d="M5 17v3h14v-3"></path>',
      '</svg>'
    ].join('');

    var qrButton = createSubscribeActionButton('qr', '扫描二维码', qrIcon);
    var copyButton = createSubscribeActionButton('copy', '复制订阅链接', copyIcon);
    var downloadButton = createSubscribeActionButton('download', '下载客户端', downloadIcon);

    qrButton.addEventListener('click', openSubscribeQrCode);
    copyButton.addEventListener('click', function () {
      copySubscribeUrl();
    });
    downloadButton.addEventListener('click', openDownloadClient);

    panel.appendChild(qrButton);
    panel.appendChild(copyButton);
    panel.appendChild(downloadButton);
    return panel;
  }

  function formatTraffic(bytes) {
    var value = Number(bytes) || 0;
    var gb = 1073741824;
    var mb = 1048576;
    if (value >= gb) return (value / gb).toFixed(2).replace(/\.00$/, '') + ' GB';
    if (value >= mb) return (value / mb).toFixed(2).replace(/\.00$/, '') + ' MB';
    return Math.max(0, value).toFixed(0) + ' B';
  }

  function isSubscriptionExpired(info) {
    return info && info.expired_at !== null && Number(info.expired_at) <= Math.floor(Date.now() / 1000);
  }

  function getActiveProductName(info) {
    if (!info) return '';
    if (info.active_product_type === 'traffic_package') {
      return info.active_product_name
        || (info.latest_traffic_package && info.latest_traffic_package.name)
        || '流量包';
    }
    if (info.active_product_type === 'plan') {
      return info.active_product_name || (info.plan && info.plan.name) || '';
    }
    return '';
  }

  function restoreSubscribeCardProduct(main) {
    var panel = main.querySelector(':scope > .er-active-product-panel');
    if (panel) panel.remove();

    Array.prototype.forEach.call(main.children, function (child) {
      if (child.getAttribute('data-er-product-hidden') === 'true') {
        child.style.display = '';
        child.removeAttribute('data-er-product-hidden');
      }
    });
  }

  function updateSubscribeCardProduct(main, info) {
    var activeProductName = getActiveProductName(info);
    if (!info || info.active_product_type !== 'traffic_package' || !activeProductName) {
      restoreSubscribeCardProduct(main);
      return;
    }

    var panel = main.querySelector(':scope > .er-active-product-panel');
    if (!panel) {
      panel = document.createElement('div');
      panel.className = 'er-active-product-panel';
      main.insertBefore(panel, main.firstChild);
    }

    Array.prototype.forEach.call(main.children, function (child) {
      if (
        child === panel
        || child.classList.contains('er-traffic-package-summary')
        || child.classList.contains('er-traffic-package-cta')
      ) {
        return;
      }
      child.setAttribute('data-er-product-hidden', 'true');
      child.style.display = 'none';
    });

    panel.innerHTML = '';
    var name = document.createElement('div');
    name.className = 'er-active-product-name';
    name.textContent = activeProductName;

    var status = document.createElement('div');
    status.className = 'er-active-product-status';
    status.textContent = '流量包可用中';

    panel.appendChild(name);
    panel.appendChild(status);
  }

  function createTrafficMetric(label, value, className) {
    var item = document.createElement('div');
    item.className = 'er-traffic-package-item ' + className;

    var labelNode = document.createElement('span');
    labelNode.className = 'er-traffic-package-label';
    labelNode.textContent = label;

    var valueNode = document.createElement('strong');
    valueNode.className = 'er-traffic-package-value';
    valueNode.textContent = value;

    item.appendChild(labelNode);
    item.appendChild(valueNode);
    return item;
  }

  function goToTrafficPackagePurchase(event) {
    if (event) {
      event.preventDefault();
      event.stopPropagation();
    }

    fetchTrafficPackages().then(function (packages) {
      if (!packages.length) {
        notify('warning', '暂无可购买流量包');
        return;
      }
      showTrafficPackageDrawer(packages);
    }).catch(function (error) {
      console.warn('流量包购买入口加载失败:', error);
      notify('error', '流量包加载失败');
    });
  }

  function formatTrafficPackagePrice(price) {
    var cents = Number(price) || 0;
    return '¥' + (cents / 100).toFixed(2).replace(/\.00$/, '');
  }

  function removeTrafficPackageDrawer() {
    var existing = document.querySelector('.er-traffic-package-drawer');
    if (existing) existing.remove();
  }

  function showTrafficPackageDrawer(packages) {
    removeTrafficPackageDrawer();

    var drawer = document.createElement('div');
    drawer.className = 'er-traffic-package-drawer';

    var panel = document.createElement('div');
    panel.className = 'er-traffic-package-drawer-panel';

    var header = document.createElement('div');
    header.className = 'er-traffic-package-drawer-header';

    var title = document.createElement('strong');
    title.textContent = '选择流量包';

    var close = document.createElement('button');
    close.type = 'button';
    close.className = 'er-traffic-package-drawer-close';
    close.setAttribute('aria-label', '关闭');
    close.textContent = '×';
    close.addEventListener('click', removeTrafficPackageDrawer);

    header.appendChild(title);
    header.appendChild(close);
    panel.appendChild(header);

    var list = document.createElement('div');
    list.className = 'er-traffic-package-list';

    packages.forEach(function (item) {
      var card = document.createElement('div');
      card.className = 'er-traffic-package-card';

      var name = document.createElement('strong');
      name.className = 'er-traffic-package-card-name';
      name.textContent = item.name || '流量包';

      var meta = document.createElement('span');
      meta.className = 'er-traffic-package-card-meta';
      meta.textContent = formatTraffic((Number(item.transfer_enable) || 0) * 1073741824);

      var price = document.createElement('span');
      price.className = 'er-traffic-package-card-price';
      price.textContent = formatTrafficPackagePrice(item.price);

      var buy = document.createElement('button');
      buy.type = 'button';
      buy.className = 'er-traffic-package-buy';
      buy.textContent = '购买';
      buy.addEventListener('click', function () {
        buy.disabled = true;
        buy.textContent = '创建订单中';
        createTrafficPackageOrder(item.id).then(function (tradeNo) {
          removeTrafficPackageDrawer();
          window.location.hash = '#/order/' + encodeURIComponent(tradeNo);
        }).catch(function (error) {
          console.warn('流量包订单创建失败:', error);
          buy.disabled = false;
          buy.textContent = '购买';
          notify('error', '订单创建失败');
        });
      });

      card.appendChild(name);
      card.appendChild(meta);
      card.appendChild(price);
      card.appendChild(buy);
      list.appendChild(card);
    });

    panel.appendChild(list);
    drawer.appendChild(panel);
    drawer.addEventListener('click', function (event) {
      if (event.target === drawer) removeTrafficPackageDrawer();
    });
    document.body.appendChild(drawer);
  }

  function renderTrafficPackageCta(main, hasPackages) {
    var existing = main.querySelector(':scope > .er-traffic-package-cta');
    if (!hasPackages) {
      if (existing) existing.remove();
      return;
    }

    if (existing) return;

    var cta = document.createElement('div');
    cta.className = 'er-traffic-package-cta';
    var label = document.createElement('span');
    label.className = 'er-traffic-package-cta-label';
    label.textContent = '流量不够用？';

    var action = document.createElement('button');
    action.type = 'button';
    action.className = 'er-traffic-package-cta-action';
    action.textContent = '立即购买流量包';
    action.addEventListener('click', goToTrafficPackagePurchase);

    cta.appendChild(label);
    cta.appendChild(action);

    main.insertBefore(cta, main.querySelector(':scope > .er-traffic-package-summary'));
  }

  function applyTrafficPackageCta(main) {
    if (!main) return false;

    fetchTrafficPackages().then(function (packages) {
      if (!isDashboardRoute() || !document.body.contains(main)) return;
      renderTrafficPackageCta(main, packages.length > 0);
    }).catch(function (error) {
      renderTrafficPackageCta(main, false);
      console.warn('流量包购买提示加载失败:', error);
    });

    return true;
  }

  function renderTrafficPackageSummary(main, info) {
    var existing = main.querySelector(':scope > .er-traffic-package-summary');
    var trafficPackageRemaining = Number(info && info.traffic_package_remaining) || 0;
    if (trafficPackageRemaining <= 0) {
      if (existing) existing.remove();
      return;
    }

    var planRemaining = Math.max(0, Number(info.plan_remaining_traffic) || 0);
    var effectiveRemaining = Math.max(
      trafficPackageRemaining,
      Number(info.effective_remaining_traffic) || planRemaining + trafficPackageRemaining
    );
    var hasActivePlan = Boolean(info && info.has_active_plan);
    var currentUsable = hasActivePlan ? effectiveRemaining : trafficPackageRemaining;
    var meterTotal = Math.max(1, planRemaining + trafficPackageRemaining);
    var planWidth = hasActivePlan ? Math.max(0, Math.min(100, (planRemaining / meterTotal) * 100)) : 0;
    var packageWidth = Math.max(0, Math.min(100, (trafficPackageRemaining / meterTotal) * 100));

    var summary = existing || document.createElement('section');
    summary.className = 'er-traffic-package-summary';
    summary.innerHTML = '';
    summary.setAttribute('aria-label', '流量构成');

    var header = document.createElement('div');
    header.className = 'er-traffic-package-header';

    var title = document.createElement('div');
    title.className = 'er-traffic-package-title';
    title.textContent = '流量构成';

    var badge = document.createElement('span');
    badge.className = 'er-traffic-package-badge';
    badge.textContent = hasActivePlan ? '优先扣套餐' : '流量包可用中';

    header.appendChild(title);
    header.appendChild(badge);

    var metrics = document.createElement('div');
    metrics.className = 'er-traffic-package-metrics';
    metrics.appendChild(createTrafficMetric('基础套餐剩余', hasActivePlan ? formatTraffic(planRemaining) : '已到期', 'er-traffic-package-item-plan'));
    metrics.appendChild(createTrafficMetric('流量包剩余', formatTraffic(trafficPackageRemaining), 'er-traffic-package-item-package'));
    metrics.appendChild(createTrafficMetric('有效可用', formatTraffic(currentUsable), 'er-traffic-package-item-effective'));

    var meter = document.createElement('div');
    meter.className = 'er-traffic-package-meter';
    meter.setAttribute('aria-hidden', 'true');

    var planSegment = document.createElement('span');
    planSegment.className = 'er-traffic-package-meter-plan';
    planSegment.style.width = planWidth + '%';

    var packageSegment = document.createElement('span');
    packageSegment.className = 'er-traffic-package-meter-package';
    packageSegment.style.width = packageWidth + '%';

    meter.appendChild(planSegment);
    meter.appendChild(packageSegment);

    var note = document.createElement('p');
    note.className = 'er-traffic-package-note';
    note.textContent = hasActivePlan
      ? '优先使用套餐流量，用完后继续使用流量包余额。套餐重置时间不受影响。'
      : '当前套餐已到期，仍可继续使用流量包余额。续费后套餐重置时间独立计算。';

    summary.appendChild(header);
    summary.appendChild(metrics);
    summary.appendChild(meter);
    summary.appendChild(note);

    if (!existing) {
      main.appendChild(summary);
    }
  }

  function applyTrafficPackageSummary(main) {
    if (!main) return false;

    fetchSubscribeInfo().then(function (info) {
      if (!isDashboardRoute() || !document.body.contains(main)) return;
      updateSubscribeCardProduct(main, info);
      renderTrafficPackageSummary(main, info);
    }).catch(function (error) {
      console.warn('流量包余额展示加载失败:', error);
    });

    return true;
  }

  function applySubscribeActions() {
    if (!isDashboardRoute()) {
      removeSubscribeActions();
      return true;
    }

    var card = findSubscribeCard();
    if (!card) return false;

    var content = card.querySelector('.n-card__content');
    if (!content || !shouldEnhanceSubscribeCard(content)) {
      removeSubscribeActions();
      return true;
    }

    var layout = content.querySelector(':scope > .er-subscribe-layout');
    var main = layout ? layout.querySelector(':scope > .er-subscribe-main') : null;
    if (!layout) {
      layout = document.createElement('div');
      layout.className = 'er-subscribe-layout';

      main = document.createElement('div');
      main.className = 'er-subscribe-main';

      while (content.firstChild) {
        main.appendChild(content.firstChild);
      }

      layout.appendChild(main);
      content.appendChild(layout);
    }

    if (!main) return false;

    if (!layout.querySelector('#er-subscribe-action-panel')) {
      layout.appendChild(createSubscribeActionPanel());
    }

    applyTrafficPackageCta(main);
    applyTrafficPackageSummary(main);
    return true;
  }

  function replaceText(root, from, to) {
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    var node;
    while ((node = walker.nextNode())) {
      if (node.nodeValue.indexOf(from) !== -1) {
        node.nodeValue = node.nodeValue.replace(from, to);
      }
    }
  }

  function findTextElementInRoot(root, text) {
    var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
    var node;
    while ((node = walker.nextNode())) {
      if (node.nodeValue && node.nodeValue.indexOf(text) !== -1) {
        return node.parentElement;
      }
    }
    return null;
  }

  function createProblemAppealIcon(existingClassName) {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', [existingClassName, 'er-dashboard-support-icon'].filter(Boolean).join(' '));
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2.25');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.innerHTML = [
      '<path d="M4 12a8 8 0 0 1 16 0"></path>',
      '<path d="M4 12v4a2 2 0 0 0 2 2h1v-6H6a2 2 0 0 0-2 2"></path>',
      '<path d="M20 12v4a2 2 0 0 1-2 2h-1v-6h1a2 2 0 0 1 2 2"></path>',
      '<path d="M18 19c0 1.1-1.8 2-4 2h-2"></path>'
    ].join('');
    return svg;
  }

  function replaceProblemAppealIcon(problemItem) {
    var icons = problemItem.querySelectorAll('svg');
    var existingClassName = icons.length > 0 ? icons[icons.length - 1].getAttribute('class') : '';
    var svg = createProblemAppealIcon(existingClassName);
    if (icons.length > 0) {
      icons[icons.length - 1].replaceWith(svg);
      return;
    }

    problemItem.appendChild(svg);
  }

  function applyProblemAppealTitleColor(problemItem) {
    var title = findTextElementInRoot(problemItem, PROBLEM_APPEAL_LABEL);
    if (title) title.style.color = PROBLEM_APPEAL_TITLE_COLOR;
  }

  function findDashboardShortcut(text) {
    var targetText = findTextElement(text);
    return targetText ? closestShortcut(targetText) : null;
  }

  function removeRenewShortcut() {
    var renewItem = findDashboardShortcut('续费订阅');
    if (!renewItem) return false;

    renewItem.remove();
    return true;
  }

  function findProblemAppealShortcut() {
    return findDashboardShortcut(PROBLEM_APPEAL_LABEL) || findDashboardShortcut('遇到问题');
  }

  function normalizeProblemAppealShortcut() {
    var problemItem = findProblemAppealShortcut();
    if (!problemItem) return false;

    replaceText(problemItem, '遇到问题可以通过工单与我们沟通', PROBLEM_APPEAL_DESCRIPTION);
    replaceText(problemItem, '遇到问题', PROBLEM_APPEAL_LABEL);
    problemItem.setAttribute('aria-label', PROBLEM_APPEAL_LABEL);
    applyProblemAppealTitleColor(problemItem);
    replaceProblemAppealIcon(problemItem);
    bindProblemAppealAiSupport(problemItem);
    return true;
  }

  function bindProblemAppealAiSupport(problemItem) {
    if (!problemItem || problemItem.dataset.erAiSupportBound === '1') return;

    problemItem.dataset.erAiSupportBound = '1';
    problemItem.addEventListener('click', function (event) {
      event.preventDefault();
      openDifySupportChat();
    });
    problemItem.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        openDifySupportChat();
      }
    });
  }

  function moveProblemAppealAfterSubscribe() {
    var subscribeItem = findDashboardShortcut('一键订阅');
    var problemItem = findProblemAppealShortcut();
    if (!subscribeItem || !problemItem || subscribeItem === problemItem || !subscribeItem.parentElement) {
      return false;
    }

    if (problemItem !== subscribeItem.nextSibling) {
      subscribeItem.parentElement.insertBefore(problemItem, subscribeItem.nextSibling);
    }
    return true;
  }

  function applyDashboardShortcutMenu() {
    if (!isDashboardRoute()) return true;

    removeRenewShortcut();
    return normalizeProblemAppealShortcut() && moveProblemAppealAfterSubscribe();
  }

  function scheduleDownloadEntry() {
    window.clearTimeout(retryTimer);
    retryTimer = window.setTimeout(function retry() {
      applyDashboardThemeTokens();
      preloadDifySupportBubble();
      maybeAutoOpenDifySupport();
      normalizeTicketAppealWording();
      removeHiddenSidebarMenuItems();
      removeDownloadEntry();
      var subscribeApplied = applySubscribeActions();
      var shortcutMenuApplied = applyDashboardShortcutMenu();
      hideUnsupportedSurgeOption();
      enhanceKaringSubscribeOption();
      enhanceClashMiSubscribeOption();
      enhanceDesktopClashVergeSubscribeOption();
      if ((!subscribeApplied || !shortcutMenuApplied) && isDashboardRoute() && attempts < maxAttempts) {
        attempts += 1;
        retryTimer = window.setTimeout(retry, 180);
      }
    }, 0);
  }

  function resetAttempts() {
    attempts = 0;
    preloadDifySupportBubble();
    maybeAutoOpenDifySupport();
    normalizeTicketAppealWording();
    removeHiddenSidebarMenuItems();
    scheduleDownloadEntry();
  }

  document.addEventListener('DOMContentLoaded', resetAttempts);
  window.addEventListener('hashchange', resetAttempts);
  window.addEventListener('popstate', resetAttempts);

  new MutationObserver(function () {
    preloadDifySupportBubble();
    maybeAutoOpenDifySupport();
    normalizeTicketAppealWording();
    removeHiddenSidebarMenuItems();
    if (isDashboardRoute()) scheduleDownloadEntry();
  }).observe(document.documentElement, {
    childList: true,
    subtree: true
  });

  resetAttempts();
})();
