(function () {
  var DOWNLOAD_URL = '/download/index.html';
  var DOWNLOAD_DESCRIPTION = '选择适合你设备的客户端';
  var SUBSCRIBE_API_PATH = '/api/v1/user/getSubscribe';
  var SERVER_API_PATH = '/api/v1/user/server/fetch';
  var KARING_ICON_PATH = 'images/karing.png';
  var ACCESS_TOKEN_STORAGE_KEY = 'VUE_NAIVE_ACCESS_TOKEN';
  var DASHBOARD_ROUTES = ['/', '/dashboard', '/home', '/index'];
  var AUTH_ROUTES = ['/sign-in', '/sign-up', '/login', '/register', '/forgetpassword', '/forgot-password'];
  var maxAttempts = 80;
  var attempts = 0;
  var retryTimer = null;
  var nodeCompatibilityPromise = null;

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

  function childContainingText(parent, text, exclude) {
    var children = Array.prototype.slice.call(parent.children || []);
    for (var i = 0; i < children.length; i += 1) {
      if (children[i] !== exclude && getNodeText(children[i]).indexOf(text) !== -1) {
        return children[i];
      }
    }
    return null;
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

  function fetchSubscribeInfo() {
    var token = getStoredAccessToken();
    if (!token) return Promise.resolve(null);

    return fetch(SUBSCRIBE_API_PATH + '?t=' + Date.now(), {
      method: 'GET',
      headers: {
        Authorization: token,
        'Content-Language': document.documentElement.lang || 'zh-CN'
      },
      credentials: 'same-origin'
    }).then(function (response) {
      return response.json();
    }).then(function (payload) {
      var subscribeUrl = payload && payload.data && payload.data.subscribe_url;
      if (!subscribeUrl) throw new Error('missing subscribe url');
      return {
        subscribeUrl: subscribeUrl,
        title: window.settings && window.settings.title ? window.settings.title : document.title || '订阅'
      };
    });
  }

  function copySubscribeUrl() {
    return fetchSubscribeInfo().then(function (info) {
      if (!info) {
        notify('error', '未登录');
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

  function openKaringImport() {
    return fetchSubscribeInfo().then(function (info) {
      if (!info) {
        notify('error', '未登录');
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

  function findSubscribeCard() {
    var title = findTextElement('我的订阅');
    if (!title) return null;
    return closestCard(title);
  }

  function shouldEnhanceSubscribeCard(content) {
    var text = getNodeText(content);
    return Boolean(text && text.indexOf('购买订阅') === -1 && text.indexOf('已用') !== -1 && text.indexOf('总计') !== -1);
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

    var qrButton = createSubscribeActionButton('qr', '扫描二维码', qrIcon);
    var copyButton = createSubscribeActionButton('copy', '复制订阅链接', copyIcon);

    qrButton.addEventListener('click', openSubscribeQrCode);
    copyButton.addEventListener('click', function () {
      copySubscribeUrl();
    });

    panel.appendChild(qrButton);
    panel.appendChild(copyButton);
    return panel;
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
    if (!layout) {
      layout = document.createElement('div');
      layout.className = 'er-subscribe-layout';

      var main = document.createElement('div');
      main.className = 'er-subscribe-main';

      while (content.firstChild) {
        main.appendChild(content.firstChild);
      }

      layout.appendChild(main);
      content.appendChild(layout);
    }

    if (!layout.querySelector('#er-subscribe-action-panel')) {
      layout.appendChild(createSubscribeActionPanel());
    }

    return true;
  }

  function findSiblingShortcutPair(sourceTextElement, targetText) {
    var candidate = sourceTextElement;
    while (candidate && candidate !== document.body) {
      var parent = candidate.parentElement;
      if (parent) {
        var target = childContainingText(parent, targetText, candidate);
        if (target) {
          return {
            source: candidate,
            target: target
          };
        }
      }
      candidate = candidate.parentElement;
    }
    return null;
  }

  function commonAncestor(a, b) {
    var seen = [];
    var node = a;
    while (node && node !== document.body) {
      seen.push(node);
      node = node.parentElement;
    }

    node = b;
    while (node && node !== document.body) {
      if (seen.indexOf(node) !== -1) return node;
      node = node.parentElement;
    }
    return null;
  }

  function normalizeClone(clone) {
    clone.id = 'er-dashboard-download';
    clone.classList.add('er-dashboard-download-shortcut');
    clone.setAttribute('data-er-download-entry', '1');
    clone.setAttribute('aria-label', '下载客户端');

    if (clone.tagName.toLowerCase() === 'a') {
      clone.href = DOWNLOAD_URL;
      clone.setAttribute('href', DOWNLOAD_URL);
      clone.target = '_blank';
      clone.setAttribute('target', '_blank');
      clone.rel = 'noopener noreferrer';
      clone.setAttribute('rel', 'noopener noreferrer');
    } else {
      clone.setAttribute('role', 'button');
      clone.setAttribute('tabindex', '0');
    }

    clone.querySelectorAll('[id]').forEach(function (node) {
      node.removeAttribute('id');
    });

    clone.querySelectorAll('a').forEach(function (node) {
      node.href = DOWNLOAD_URL;
      node.setAttribute('href', DOWNLOAD_URL);
      node.target = '_blank';
      node.setAttribute('target', '_blank');
      node.rel = 'noopener noreferrer';
      node.setAttribute('rel', 'noopener noreferrer');
    });

    clone.querySelectorAll('button').forEach(function (node) {
      node.type = 'button';
    });

    replaceText(clone, '查看教程', '下载客户端');
    replaceText(clone, '一键订阅', '下载客户端');
    replaceText(clone, '学习如何使用 Eelphant Route', DOWNLOAD_DESCRIPTION);
    replaceText(clone, '学习如何使用 Elephant Route', DOWNLOAD_DESCRIPTION);
    replaceText(clone, '不会使用，查看使用教程', DOWNLOAD_DESCRIPTION);
    replaceText(clone, '学习如何使用', DOWNLOAD_DESCRIPTION);
    replaceText(clone, '使用支持扫码的客户端进行订阅', DOWNLOAD_DESCRIPTION);
    replaceText(clone, '快速将节点导入对应客户端进行使用', DOWNLOAD_DESCRIPTION);
    replaceDownloadIcon(clone);

    if (clone.dataset.erDownloadBound !== '1') {
      clone.dataset.erDownloadBound = '1';
      clone.addEventListener('click', function (event) {
        if (event.target.closest('a[href="' + DOWNLOAD_URL + '"]')) return;
        event.preventDefault();
        window.open(DOWNLOAD_URL, '_blank', 'noopener,noreferrer');
      });

      clone.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          window.open(DOWNLOAD_URL, '_blank', 'noopener,noreferrer');
        }
      });
    }
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

  function replaceDownloadIcon(root) {
    var svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'er-dashboard-download-icon');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('fill', 'none');
    svg.setAttribute('stroke', 'currentColor');
    svg.setAttribute('stroke-width', '2.25');
    svg.setAttribute('stroke-linecap', 'round');
    svg.setAttribute('stroke-linejoin', 'round');
    svg.innerHTML = [
      '<path d="M12 3v11"></path>',
      '<path d="m7 10 5 5 5-5"></path>',
      '<path d="M5 17v3h14v-3"></path>'
    ].join('');

    var icons = root.querySelectorAll('svg');
    if (icons.length > 0) {
      icons[icons.length - 1].replaceWith(svg);
      return;
    }

    var iconWrap = document.createElement('span');
    iconWrap.className = 'er-dashboard-download-icon-wrap';
    iconWrap.appendChild(svg);
    root.appendChild(iconWrap);
  }

  function applyDownloadEntry() {
    if (!isDashboardRoute()) {
      removeDownloadEntry();
      return true;
    }

    var existing = document.getElementById('er-dashboard-download');
    if (existing) {
      if (existing.classList.contains('er-dashboard-download-shortcut')) {
        normalizeClone(existing);
        return true;
      }
      existing.remove();
    }

    var tutorialText = findTextElement('查看教程');
    var subscribeText = findTextElement('一键订阅');
    if (!tutorialText || !subscribeText) return false;

    var pair = findSiblingShortcutPair(tutorialText, '一键订阅');
    var tutorialItem = pair ? pair.source : closestShortcut(tutorialText);
    var subscribeItem = pair ? pair.target : closestShortcut(subscribeText);
    if (!tutorialItem || !subscribeItem || tutorialItem === subscribeItem) return false;

    var container = commonAncestor(tutorialItem, subscribeItem);
    if (!container || !subscribeItem.parentElement) return false;

    var clone = tutorialItem.cloneNode(true);
    normalizeClone(clone);
    subscribeItem.parentElement.insertBefore(clone, subscribeItem);
    return true;
  }

  function scheduleDownloadEntry() {
    window.clearTimeout(retryTimer);
    retryTimer = window.setTimeout(function retry() {
      var applied = applyDownloadEntry();
      var subscribeApplied = applySubscribeActions();
      hideUnsupportedSurgeOption();
      enhanceKaringSubscribeOption();
      if ((!applied || !subscribeApplied) && isDashboardRoute() && attempts < maxAttempts) {
        attempts += 1;
        retryTimer = window.setTimeout(retry, 180);
      }
    }, 0);
  }

  function resetAttempts() {
    attempts = 0;
    scheduleDownloadEntry();
  }

  document.addEventListener('DOMContentLoaded', resetAttempts);
  window.addEventListener('hashchange', resetAttempts);
  window.addEventListener('popstate', resetAttempts);

  new MutationObserver(function () {
    if (isDashboardRoute()) scheduleDownloadEntry();
  }).observe(document.documentElement, {
    childList: true,
    subtree: true
  });

  resetAttempts();
})();
