<!DOCTYPE html>
<html>
  <head>
    <title>{{$title}}</title>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
    <meta name="renderer" content="webkit" />
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
    <meta name="description" content="{{ $description }}" />
    
    @empty($logo)
    <link rel="icon" href="/theme/{{$theme}}/favicon.svg" />
    @endempty
    <link rel="icon" href="{{ $logo }}" />
  
    <link rel="stylesheet" href="/theme/{{$theme}}/static/phosphor-icons/duotone/style.css" />
    <link rel="stylesheet" href="/theme/{{$theme}}/static/phosphor-icons/regular/style.css" />
    
  @if (file_exists(public_path("/theme/{$theme}/static/custom.css")))
    <link rel="stylesheet" href="/theme/{{$theme}}/static/custom.css?v=20231102012645" />
  @endif
  
    <style>
      html,
      body {
        height: 100%;
        margin: 0;
      }
      a {
        color: inherit;
        text-decoration: none;
      }
      .is-darkmode {
        background-color: #272827;
      }
      .is-darkmode .loading-user:before {
        display: none;
      }
      .hourglassx {
        width: 120px;
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
      }
      .hourglass {
        stroke-dasharray: 210;
        -webkit-animation: snake 3s linear infinite both;
        animation: snake 3s linear infinite both;
      }
      .loading-user {
        width: 40%;
        text-align: center;
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        font-size: 24px;
        color: #999;
        overflow: hidden;
      }

      .loading-user:before {
        content: '';
        position: absolute;
        left: -100px;
        top: -80px;
        width: 16px;
        height: 400px;
        background-color: rgba(255, 255, 255, 0.8);
        transform: rotate(45deg);
        animation: flash 1.5s linear 0.1s infinite;
      }
      @-webkit-keyframes snake {
        0% {
          stroke-dashoffset: 0;
        }
        100% {
          stroke-dashoffset: 420;
        }
      }
      @keyframes snake {
        0% {
          stroke-dashoffset: 0;
        }
        100% {
          stroke-dashoffset: 420;
        }
      }
      @-webkit-keyframes flash {
        0% {
          left: -100px;
        }
        100% {
          left: 100%;
        }
      }
      @keyframes flash {
        0% {
          left: -100px;
        }
        100% {
          left: 100%;
        }
      }
    </style>
  <link href="/theme/{{$theme}}/static/css/n.960f0d5f.css" rel="stylesheet"><link href="/theme/{{$theme}}/static/css/app.9a999ca1.css" rel="stylesheet"></head>
  <body>
    <noscript>
      <strong>Browser Disable JavaScript, Please Enable.</strong>
    </noscript>
    
    @empty($theme_config['loading_text'])
    
    <svg version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" class="hourglassx" x="0px" y="0px" viewBox="0 0 203 203" enable-background="new 0 0 203 203" xml:space="preserve">
      <g>
        <path
          class="hourglass"
          fill="none"
          stroke="#C0E5FA"
          stroke-width="5"
          stroke-linecap="round"
          stroke-miterlimit="10"
          d="M137.5,169.5h-72
		c0-72,63-73,63-126h-54C74.5,96.5,137.5,97.5,137.5,169.5z"
        />
        <path
          class="hourglass"
          fill="none"
          stroke="#74C2EE"
          stroke-width="5"
          stroke-linecap="round"
          stroke-miterlimit="10"
          d="M65.5,34.5h72
		c0,71-63,71-63,126h54C128.5,105.5,65.5,105.5,65.5,34.5z"
        />
      </g>
    </svg>
  
    @endempty
    <div class="loading-user">{!! $theme_config['loading_text'] !!}</div>
  
    <div id="app"></div>
    
      <script>
      window.EnvConfig = {
        serverUrl: '{{ $theme_config['server_url'] }}',
        landPage: '{{ $theme_config['land_page'] }}',
        showRegInvite: '{{ $theme_config['show_reg_invite'] }}',
        appTheme: '{{ $theme_config['app_theme'] }}',
        appColor: '{{ $theme_config['app_color'] }}',
        appName: '{{ $title }}',
        appDesc: `{{ $description }}`,
        appLogo: '{{ $logo }}',
        appVersion: '{{ $version }}',
        clientIOS: '{{ $theme_config['client_ios'] }}',
        clientAndroid: '{{ $theme_config['client_android'] }}',
        clientWindows: '{{ $theme_config['client_windows'] }}',
        clientMacOS: '{{ $theme_config['client_macos'] }}',
        clientOpenwrt: '{{ $theme_config['client_openwrt'] }}',
        clientLinux: '{{ $theme_config['client_linux'] }}',
        staticUrl: '/theme/{{ $theme }}/static'
      }
      </script>
    
    <script>
      window.langs = {}
      function isDarkMode() {
        var themeMedia = window.matchMedia('(prefers-color-scheme: dark)')
        var isDark = false
        var localMode = JSON.parse(localStorage.getItem('__AURORA__Darkmode') || '{}').value

        if (localMode !== undefined) {
          isDark = localMode === 'dark'
        } else if (EnvConfig.appTheme === 'dark') {
          isDark = true
        } else if (EnvConfig.appTheme === 'auto') {
          isDark = themeMedia.matches
        }
        return isDark
      }
      if (isDarkMode()) {
        document.body.classList.add('is-darkmode')
      }
      document.body.classList.add(EnvConfig.appColor)

      function getLocaleLang() {
        try {
          var str = localStorage.getItem('__AURORA__Language') || '{}'
          var res = JSON.parse(str).value
          if (res) {
            return res.substring(0, 2) + '-' + res.substring(2)
          }
        } catch (e) {
          return undefined
        }
      }
    </script>
    {!! $theme_config['custom_html'] !!}
    <script src="/theme/{{$theme}}/expose.js?v=20231102012645"></script>
    <script src="/theme/{{$theme}}/static/i18n/zh-CN.js?v=20231102012645"></script>
    <script src="/theme/{{$theme}}/static/i18n/zh-TW.js?v=20231102012645"></script>
    <script src="/theme/{{$theme}}/static/i18n/en-US.js?v=20231102012645"></script>
    <!-- built files will be auto injected -->
    
  @if (file_exists(public_path("/theme/{$theme}/static/custom.js")))
    <script src="/theme/{{$theme}}/static/custom.js?v=20231102012645"></script>
  @endif
  
  <script>(function(e){function c(c){for(var t,u,f=c[0],h=c[1],d=c[2],o=0,k=[];o<f.length;o++)u=f[o],Object.prototype.hasOwnProperty.call(a,u)&&a[u]&&k.push(a[u][0]),a[u]=0;for(t in h)Object.prototype.hasOwnProperty.call(h,t)&&(e[t]=h[t]);i&&i(c);while(k.length)k.shift()();return r.push.apply(r,d||[]),n()}function n(){for(var e,c=0;c<r.length;c++){for(var n=r[c],t=!0,u=1;u<n.length;u++){var f=n[u];0!==a[f]&&(t=!1)}t&&(r.splice(c--,1),e=h(h.s=n[0]))}return e}var t={},u={runtime:0},a={runtime:0},r=[];function f(e){return h.p+"static/js/"+({}[e]||e)+"."+{"chunk-131c13e9":"d4a196d6","chunk-4a44ccd3":"40f4186c","chunk-607f2d24":"b5dea78a","chunk-3f085023":"b5b217fc","chunk-2d0aa5b8":"9e7c68a8","chunk-6e83591c":"9e852703","chunk-24f7a0d6":"4b64dfad","chunk-59e0bc55":"a588a8e8","chunk-78d4ca10":"b9a78141","chunk-8c5d225c":"73e70d96","chunk-6462ad91":"385425d9","chunk-7e75c5a6":"d7953a40","chunk-a5232a28":"20662d6c","chunk-d4acb0c8":"427f6c79","chunk-8ce954c8":"1c04407c","chunk-2d21d665":"09e5fcba","chunk-3548057f":"bb7579a3","chunk-360fb284":"5bfe9f12","chunk-38cf90e9":"67de2661","chunk-4cdaad7c":"e033aeb4","chunk-562c69ae":"3035439a","chunk-9806f83e":"2ee2d89b","chunk-12f016f3":"e634e961","chunk-753cdac9":"19d0f5f6","chunk-79e2d36c":"0c7eab56","chunk-6bb7a56f":"36247e11","chunk-6765a98f":"2f9356fe","chunk-d24ef460":"63c4bc46","chunk-b418fdba":"0d2942d7","chunk-bf9939ba":"ec6f2545"}[e]+".js"}function h(c){if(t[c])return t[c].exports;var n=t[c]={i:c,l:!1,exports:{}};return e[c].call(n.exports,n,n.exports,h),n.l=!0,n.exports}h.e=function(e){var c=[],n={"chunk-4a44ccd3":1,"chunk-3f085023":1,"chunk-59e0bc55":1,"chunk-78d4ca10":1,"chunk-6462ad91":1,"chunk-7e75c5a6":1,"chunk-a5232a28":1,"chunk-d4acb0c8":1,"chunk-8ce954c8":1,"chunk-3548057f":1,"chunk-38cf90e9":1,"chunk-4cdaad7c":1,"chunk-562c69ae":1,"chunk-12f016f3":1,"chunk-753cdac9":1,"chunk-79e2d36c":1,"chunk-6765a98f":1,"chunk-d24ef460":1,"chunk-b418fdba":1,"chunk-bf9939ba":1};u[e]?c.push(u[e]):0!==u[e]&&n[e]&&c.push(u[e]=new Promise((function(c,n){for(var t="static/css/"+({}[e]||e)+"."+{"chunk-131c13e9":"31d6cfe0","chunk-4a44ccd3":"c6340983","chunk-607f2d24":"31d6cfe0","chunk-3f085023":"abaef767","chunk-2d0aa5b8":"31d6cfe0","chunk-6e83591c":"31d6cfe0","chunk-24f7a0d6":"31d6cfe0","chunk-59e0bc55":"64613b28","chunk-78d4ca10":"6f1ab7b9","chunk-8c5d225c":"31d6cfe0","chunk-6462ad91":"54f4a085","chunk-7e75c5a6":"3c49e56e","chunk-a5232a28":"3c49e56e","chunk-d4acb0c8":"9c5371bc","chunk-8ce954c8":"2ef150e1","chunk-2d21d665":"31d6cfe0","chunk-3548057f":"47543bb1","chunk-360fb284":"31d6cfe0","chunk-38cf90e9":"b45a908c","chunk-4cdaad7c":"58d9ccef","chunk-562c69ae":"1661c444","chunk-9806f83e":"31d6cfe0","chunk-12f016f3":"1d680551","chunk-753cdac9":"2d97752d","chunk-79e2d36c":"3e588de0","chunk-6bb7a56f":"31d6cfe0","chunk-6765a98f":"9f3e51a6","chunk-d24ef460":"5aa4a559","chunk-b418fdba":"a82ec784","chunk-bf9939ba":"3ac0e82e"}[e]+".css",a=h.p+t,r=document.getElementsByTagName("link"),f=0;f<r.length;f++){var d=r[f],o=d.getAttribute("data-href")||d.getAttribute("href");if("stylesheet"===d.rel&&(o===t||o===a))return c()}var k=document.getElementsByTagName("style");for(f=0;f<k.length;f++){d=k[f],o=d.getAttribute("data-href");if(o===t||o===a)return c()}var i=document.createElement("link");i.rel="stylesheet",i.type="text/css",i.onload=c,i.onerror=function(c){var t=c&&c.target&&c.target.src||a,r=new Error("Loading CSS chunk "+e+" failed.\n("+t+")");r.code="CSS_CHUNK_LOAD_FAILED",r.request=t,delete u[e],i.parentNode.removeChild(i),n(r)},i.href=a;var b=document.getElementsByTagName("head")[0];b.appendChild(i)})).then((function(){u[e]=0})));var t=a[e];if(0!==t)if(t)c.push(t[2]);else{var r=new Promise((function(c,n){t=a[e]=[c,n]}));c.push(t[2]=r);var d,o=document.createElement("script");o.charset="utf-8",o.timeout=120,h.nc&&o.setAttribute("nonce",h.nc),o.src=f(e);var k=new Error;d=function(c){o.onerror=o.onload=null,clearTimeout(i);var n=a[e];if(0!==n){if(n){var t=c&&("load"===c.type?"missing":c.type),u=c&&c.target&&c.target.src;k.message="Loading chunk "+e+" failed.\n("+t+": "+u+")",k.name="ChunkLoadError",k.type=t,k.request=u,n[1](k)}a[e]=void 0}};var i=setTimeout((function(){d({type:"timeout",target:o})}),12e4);o.onerror=o.onload=d,document.head.appendChild(o)}return Promise.all(c)},h.m=e,h.c=t,h.d=function(e,c,n){h.o(e,c)||Object.defineProperty(e,c,{enumerable:!0,get:n})},h.r=function(e){"undefined"!==typeof Symbol&&Symbol.toStringTag&&Object.defineProperty(e,Symbol.toStringTag,{value:"Module"}),Object.defineProperty(e,"__esModule",{value:!0})},h.t=function(e,c){if(1&c&&(e=h(e)),8&c)return e;if(4&c&&"object"===typeof e&&e&&e.__esModule)return e;var n=Object.create(null);if(h.r(n),Object.defineProperty(n,"default",{enumerable:!0,value:e}),2&c&&"string"!=typeof e)for(var t in e)h.d(n,t,function(c){return e[c]}.bind(null,t));return n},h.n=function(e){var c=e&&e.__esModule?function(){return e["default"]}:function(){return e};return h.d(c,"a",c),c},h.o=function(e,c){return Object.prototype.hasOwnProperty.call(e,c)},h.p="/theme/{{$theme}}/",h.oe=function(e){throw console.error(e),e};var d=window["webpackJsonp"]=window["webpackJsonp"]||[],o=d.push.bind(d);d.push=c,d=d.slice();for(var k=0;k<d.length;k++)c(d[k]);var i=o;n()})([]);</script><script type="text/javascript" src="/theme/{{$theme}}/static/js/n.c1cc6f8e.js"></script><script type="text/javascript" src="/theme/{{$theme}}/static/js/app.9a3749eb.js"></script></body>
</html>
