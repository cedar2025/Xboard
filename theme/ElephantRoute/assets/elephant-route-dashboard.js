(function () {
  var DOWNLOAD_URL = 'https://www.elphantroute.com/download/index.html';
  var DOWNLOAD_DESCRIPTION = '选择适合你设备的客户端';
  var DASHBOARD_ROUTES = ['/', '/dashboard', '/home', '/index'];
  var AUTH_ROUTES = ['/sign-in', '/sign-up', '/login', '/register', '/forgetpassword', '/forgot-password'];
  var maxAttempts = 80;
  var attempts = 0;
  var retryTimer = null;

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

  function findTextElement(text) {
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
      acceptNode: function (node) {
        if (!node.nodeValue || node.nodeValue.indexOf(text) === -1) return NodeFilter.FILTER_REJECT;
        if (node.parentElement && node.parentElement.closest('#er-dashboard-download')) return NodeFilter.FILTER_REJECT;
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
      if (node.matches('a, button, [role="button"], .n-card, .v2board-shortcuts-item, [class*="shortcut"], [class*="Shortcut"]')) {
        return node;
      }
      node = node.parentElement;
    }
    return element;
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
      if (!applied && isDashboardRoute() && attempts < maxAttempts) {
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
