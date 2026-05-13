(function () {
  var AUTH_ROUTES = ['/sign-in', '/sign-up', '/login', '/register', '/forgetpassword', '/forgot-password'];
  var LOGO_CANDIDATES = ['/login_logo.jpeg', '/home_logo.jpeg', '/landing/assets/elephant-route-logo.jpg'];
  var HOME_PATH = '/welcome';
  var maxAttempts = 80;
  var attempts = 0;
  var observer = null;
  var retryTimer = null;

  function getRoute() {
    var hash = window.location.hash || '';
    var route = hash.replace(/^#/, '').split('?')[0] || '/';
    return route.replace(/\/$/, '') || '/';
  }

  function isAuthRoute() {
    var route = getRoute();
    return AUTH_ROUTES.some(function (item) {
      return route === item || route.indexOf(item + '/') === 0;
    });
  }

  function hasAuthForm() {
    var passwordInput = document.querySelector('input[type="password"], input[placeholder*="密码"]');
    var authLink = document.querySelector('a[href="#/register"], a[href="#/login"], a[href="#/forgetpassword"], a[href="#/forgot-password"], a[href="#/sign-up"], a[href="#/sign-in"]');
    return Boolean(passwordInput && authLink && !document.querySelector('.n-layout-sider'));
  }

  function isForgotRoute() {
    var route = getRoute();
    return route === '/forgetpassword' || route === '/forgot-password' || route.indexOf('/forgetpassword/') === 0 || route.indexOf('/forgot-password/') === 0;
  }

  function isRegisterRoute() {
    var route = getRoute();
    return route === '/sign-up' || route === '/register' || route.indexOf('/sign-up/') === 0 || route.indexOf('/register/') === 0;
  }

  function preloadLogo(img) {
    var index = 0;
    img.src = LOGO_CANDIDATES[index];
    img.onerror = function () {
      index += 1;
      if (index < LOGO_CANDIDATES.length) {
        img.src = LOGO_CANDIDATES[index];
      }
    };
  }

  function removeAuthEnhancement() {
    document.documentElement.classList.remove('er-auth-active');
    document.body.classList.remove('er-auth-page', 'er-auth-login', 'er-auth-register');
    var visual = document.getElementById('er-auth-visual');
    if (visual) visual.remove();
  }

  function buildVisual(copy) {
    var visual = document.createElement('section');
    visual.id = 'er-auth-visual';
    visual.className = 'er-auth-visual';
    visual.setAttribute('aria-label', '大象网络品牌介绍');
    visual.innerHTML = [
      '<p class="er-auth-kicker">Elephant Route</p>',
      '<h1 class="er-auth-title">' + copy.heroTitle + '</h1>',
      '<p class="er-auth-subtitle">' + copy.heroSubtitle + '</p>',
      '<div class="er-auth-art" aria-hidden="true">',
      '  <span class="er-auth-mark er-auth-character"><i class="er-auth-eye er-auth-eye-left"><b></b></i><i class="er-auth-eye er-auth-eye-right"><b></b></i></span>',
      '  <span class="er-auth-device er-auth-device-one er-auth-character"><i class="er-auth-eye er-auth-eye-left"><b></b></i><i class="er-auth-eye er-auth-eye-right"><b></b></i></span>',
      '  <span class="er-auth-device er-auth-device-two er-auth-character"><i class="er-auth-eye er-auth-eye-left"><b></b></i><i class="er-auth-eye er-auth-eye-right"><b></b></i></span>',
      '  <span class="er-auth-device er-auth-device-three er-auth-character"><i class="er-auth-eye er-auth-eye-left"><b></b></i><i class="er-auth-eye er-auth-eye-right"><b></b></i><span class="er-auth-mouth"></span></span>',
      '</div>'
    ].join('');
    document.body.insertBefore(visual, document.body.firstChild);
    bindEyeTracking(visual.querySelector('.er-auth-art'));
  }

  function bindEyeTracking(art) {
    if (!art || art.dataset.eyeTrackingReady === '1') return;
    art.dataset.eyeTrackingReady = '1';

    function setEyeOffset(x, y) {
      art.style.setProperty('--er-eye-x', x.toFixed(2) + 'px');
      art.style.setProperty('--er-eye-y', y.toFixed(2) + 'px');
    }

    function updateEyes(event) {
      var rect = art.getBoundingClientRect();
      var centerX = rect.left + rect.width / 2;
      var centerY = rect.top + rect.height / 2;
      var dx = (event.clientX - centerX) / (rect.width / 2);
      var dy = (event.clientY - centerY) / (rect.height / 2);
      var distance = Math.min(Math.sqrt(dx * dx + dy * dy), 1);
      var angle = Math.atan2(dy, dx);
      setEyeOffset(Math.cos(angle) * distance * 4.8, Math.sin(angle) * distance * 3.8);
    }

    function resetEyes() {
      setEyeOffset(0, 0);
    }

    art.addEventListener('pointermove', updateEyes);
    art.addEventListener('mousemove', updateEyes);
    art.addEventListener('pointerleave', resetEyes);
    window.addEventListener('mousemove', updateEyes, { passive: true });
    window.addEventListener('blur', resetEyes);
    resetEyes();
  }

  function ensureFormCopy(card, copy) {
    var content = card.querySelector('.n-card__content') || card;
    var originalTitle = content.querySelector(':scope > div:not(.er-auth-form-copy) > h1');
    var originalSubtitle = content.querySelector(':scope > div:not(.er-auth-form-copy) > h5');
    var resolvedTitle = originalTitle && originalTitle.textContent.trim() ? originalTitle.textContent.trim() : copy.formTitle;
    var resolvedSubtitle = originalSubtitle && originalSubtitle.textContent.trim() ? originalSubtitle.textContent.trim() : copy.formSubtitle;
    var existing = content.querySelector('.er-auth-form-copy');
    if (existing) {
      ensureBrandHomeLink(existing);
      existing.querySelector('.er-auth-form-kicker').textContent = copy.kicker;
      existing.querySelector('.er-auth-form-title').textContent = resolvedTitle;
      existing.querySelector('.er-auth-form-subtitle').textContent = resolvedSubtitle;
      return;
    }

    var block = document.createElement('div');
    block.className = 'er-auth-form-copy';
    block.innerHTML = [
      '<a class="er-auth-brand-row" href="' + HOME_PATH + '" aria-label="打开大象网络主页">',
      '  <img class="er-auth-logo" alt="大象网络" />',
      '  <span><strong class="er-auth-brand-name">大象网络</strong><small class="er-auth-brand-meta">Elephant Route</small></span>',
      '</a>',
      '<p class="er-auth-form-kicker">' + copy.kicker + '</p>',
      '<h2 class="er-auth-form-title"></h2>',
      '<p class="er-auth-form-subtitle"></p>'
    ].join('');
    preloadLogo(block.querySelector('img'));
    block.querySelector('.er-auth-form-title').textContent = resolvedTitle;
    block.querySelector('.er-auth-form-subtitle').textContent = resolvedSubtitle;
    content.insertBefore(block, content.firstChild);
  }

  function ensureBrandHomeLink(scope) {
    var brand = scope.querySelector('.er-auth-brand-row');
    if (!brand) return;
    if (brand.tagName.toLowerCase() === 'a') {
      brand.setAttribute('href', HOME_PATH);
      brand.removeAttribute('target');
      brand.setAttribute('aria-label', '打开大象网络主页');
      return;
    }

    var link = document.createElement('a');
    link.className = brand.className;
    link.href = HOME_PATH;
    link.setAttribute('aria-label', '打开大象网络主页');
    while (brand.firstChild) {
      link.appendChild(brand.firstChild);
    }
    brand.replaceWith(link);
  }

  function getCopy() {
    if (isForgotRoute()) {
      return {
        heroTitle: '全球连接，尽在掌握',
        heroSubtitle: '找回账号访问权限，继续管理你的订阅与客户端配置。',
        kicker: '找回密码',
        formTitle: '重置大象网络密码',
        formSubtitle: '验证邮箱后设置新密码，恢复你的账号访问。'
      };
    }

    if (isRegisterRoute()) {
      return {
        heroTitle: '稳定连接，从这里开始',
        heroSubtitle: '加入大象网络，获取稳定、高效的全球连接体验。',
        kicker: '创建账号',
        formTitle: '注册大象网络',
        formSubtitle: '填写账号信息后即可开始配置订阅与客户端。'
      };
    }
    return {
      heroTitle: '全球连接，尽在掌握',
      heroSubtitle: '大象网络为你的设备提供清晰、稳定、易管理的连接体验。',
      kicker: '欢迎回来',
      formTitle: '登录大象网络',
      formSubtitle: '登录大象网络，继续管理你的订阅与客户端配置。'
    };
  }

  function findAuthCard() {
    return document.querySelector('.n-card.n-card--bordered.mx-auto.max-w-md') ||
      document.querySelector('.n-card.mx-auto.max-w-md') ||
      document.querySelector('.n-card');
  }

  function applyAuthEnhancement() {
    if (!isAuthRoute() && !hasAuthForm()) {
      removeAuthEnhancement();
      return false;
    }

    var card = findAuthCard();
    if (!card) return false;

    var copy = getCopy();
    document.documentElement.classList.add('er-auth-active');
    document.body.classList.add('er-auth-page');
    document.body.classList.toggle('er-auth-forgot', isForgotRoute());
    document.body.classList.toggle('er-auth-register', isRegisterRoute());
    document.body.classList.toggle('er-auth-login', !isRegisterRoute() && !isForgotRoute());

    if (!document.getElementById('er-auth-visual')) {
      buildVisual(copy);
    } else {
      var visual = document.getElementById('er-auth-visual');
      visual.querySelector('.er-auth-title').textContent = copy.heroTitle;
      visual.querySelector('.er-auth-subtitle').textContent = copy.heroSubtitle;
      bindEyeTracking(visual.querySelector('.er-auth-art'));
    }

    ensureFormCopy(card, copy);
    return true;
  }

  function scheduleEnhancement() {
    window.clearTimeout(retryTimer);
    retryTimer = window.setTimeout(function retry() {
      var applied = applyAuthEnhancement();
      if (!applied && isAuthRoute() && attempts < maxAttempts) {
        attempts += 1;
        retryTimer = window.setTimeout(retry, 150);
      }
    }, 0);
  }

  function resetAttempts() {
    attempts = 0;
    scheduleEnhancement();
  }

  document.addEventListener('DOMContentLoaded', resetAttempts);
  window.addEventListener('hashchange', resetAttempts);
  window.addEventListener('popstate', resetAttempts);

  observer = new MutationObserver(function () {
    if (isAuthRoute() || hasAuthForm()) scheduleEnhancement();
  });

  observer.observe(document.documentElement, {
    childList: true,
    subtree: true
  });

  resetAttempts();
})();
