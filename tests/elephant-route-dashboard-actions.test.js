const fs = require('node:fs');
const path = require('node:path');
const childProcess = require('node:child_process');
const zlib = require('node:zlib');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

function readPngSize(relativePath) {
  const buffer = fs.readFileSync(path.join(repoRoot, relativePath));
  assert.equal(buffer.toString('ascii', 1, 4), 'PNG');
  return {
    width: buffer.readUInt32BE(16),
    height: buffer.readUInt32BE(20),
    bytes: buffer.length
  };
}

function readPngPixels(relativePath) {
  const buffer = fs.readFileSync(path.join(repoRoot, relativePath));
  assert.equal(buffer.toString('ascii', 1, 4), 'PNG');

  var offset = 8;
  var width = 0;
  var height = 0;
  var colorType = 0;
  var idat = [];

  while (offset < buffer.length) {
    var length = buffer.readUInt32BE(offset);
    var type = buffer.toString('ascii', offset + 4, offset + 8);
    var data = buffer.subarray(offset + 8, offset + 8 + length);
    if (type === 'IHDR') {
      width = data.readUInt32BE(0);
      height = data.readUInt32BE(4);
      assert.equal(data[8], 8, `${relativePath} must use 8-bit PNG color`);
      colorType = data[9];
    } else if (type === 'IDAT') {
      idat.push(data);
    } else if (type === 'IEND') {
      break;
    }
    offset += 12 + length;
  }

  var channels = colorType === 6 ? 4 : colorType === 2 ? 3 : 0;
  assert.ok(channels, `${relativePath} must be RGB or RGBA PNG`);

  var raw = zlib.inflateSync(Buffer.concat(idat));
  var stride = width * channels;
  var rows = [];
  var rawOffset = 0;
  var previous = Buffer.alloc(stride);

  for (var y = 0; y < height; y += 1) {
    var filter = raw[rawOffset];
    rawOffset += 1;
    var scanline = Buffer.from(raw.subarray(rawOffset, rawOffset + stride));
    rawOffset += stride;
    var row = Buffer.alloc(stride);

    for (var x = 0; x < stride; x += 1) {
      var left = x >= channels ? row[x - channels] : 0;
      var up = previous[x] || 0;
      var upLeft = x >= channels ? previous[x - channels] || 0 : 0;
      var predictor = 0;
      if (filter === 1) predictor = left;
      if (filter === 2) predictor = up;
      if (filter === 3) predictor = Math.floor((left + up) / 2);
      if (filter === 4) {
        var p = left + up - upLeft;
        var pa = Math.abs(p - left);
        var pb = Math.abs(p - up);
        var pc = Math.abs(p - upLeft);
        predictor = pa <= pb && pa <= pc ? left : pb <= pc ? up : upLeft;
      }
      row[x] = (scanline[x] + predictor) & 255;
    }

    rows.push(row);
    previous = row;
  }

  return {
    width: width,
    height: height,
    pixel: function (x, y) {
      var index = x * channels;
      return Array.from(rows[y].subarray(index, index + channels));
    }
  };
}

test('ElephantRoute dashboard override assets are synced to the public theme directory', () => {
  assert.equal(
    readRepoFile('public/theme/ElephantRoute/assets/elephant-route-dashboard.js'),
    readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.js')
  );
  assert.equal(
    readRepoFile('public/theme/ElephantRoute/assets/elephant-route-dashboard.css'),
    readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.css')
  );
});

test('ElephantRoute production dashboard override assets are not gitignored', () => {
  const publicAssets = [
    'public/theme/ElephantRoute/assets/elephant-route-dashboard.js',
    'public/theme/ElephantRoute/assets/elephant-route-dashboard.css',
    'public/theme/ElephantRoute/assets/images/clash-mi.png'
  ];

  for (const asset of publicAssets) {
    assert.throws(
      () => childProcess.execFileSync('git', ['check-ignore', '--quiet', asset], {
        cwd: repoRoot,
        stdio: 'ignore'
      }),
      `${asset} should be deployable from git, not ignored`
    );
  }
});

test('ElephantRoute Clash Verge icon asset is synced to the public theme directory', () => {
  const themeIcon = fs.readFileSync(path.join(repoRoot, 'theme/ElephantRoute/assets/images/clash-verge.png'));
  const publicIcon = fs.readFileSync(path.join(repoRoot, 'public/theme/ElephantRoute/assets/images/clash-verge.png'));

  assert.deepEqual(publicIcon, themeIcon);
});

test('ElephantRoute Clash Mi icon asset is synced to the public theme directory', () => {
  const themeIcon = fs.readFileSync(path.join(repoRoot, 'theme/ElephantRoute/assets/images/clash-mi.png'));
  const publicIcon = fs.readFileSync(path.join(repoRoot, 'public/theme/ElephantRoute/assets/images/clash-mi.png'));

  assert.deepEqual(publicIcon, themeIcon);
});

test('ElephantRoute Clash Mi icon is cropped and compressed for the subscribe modal', () => {
  for (const iconPath of [
    'theme/ElephantRoute/assets/images/clash-mi.png',
    'public/theme/ElephantRoute/assets/images/clash-mi.png'
  ]) {
    const icon = readPngSize(iconPath);
    assert.ok(icon.width <= 96, `${iconPath} width should be 96px or smaller`);
    assert.ok(icon.height <= 96, `${iconPath} height should be 96px or smaller`);
    assert.ok(icon.bytes <= 15000, `${iconPath} should be 15KB or smaller`);
  }
});

test('ElephantRoute Clash Mi icon removes non-white screenshot background', () => {
  for (const iconPath of [
    'theme/ElephantRoute/assets/images/clash-mi.png',
    'public/theme/ElephantRoute/assets/images/clash-mi.png'
  ]) {
    const image = readPngPixels(iconPath);
    const corners = [
      image.pixel(0, 0),
      image.pixel(image.width - 1, 0),
      image.pixel(0, image.height - 1),
      image.pixel(image.width - 1, image.height - 1)
    ];

    for (const pixel of corners) {
      assert.ok(pixel[0] >= 245 && pixel[1] >= 245 && pixel[2] >= 245, `${iconPath} corners should be white`);
    }
  }
});


test('ElephantRoute dashboard injects subscription action shortcuts', () => {
  const script = readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.js');

  assert.match(script, /er-subscribe-action-panel/);
  assert.match(script, /applySubscribeActions/);
  assert.match(script, /openSubscribeQrCode/);
  assert.match(script, /copySubscribeUrl/);
  assert.match(script, /er-subscribe-direct-qr-active/);
  assert.match(script, /er-subscribe-source-menu-hidden/);
  assert.match(script, /er-subscribe-source-modal-hidden/);
  assert.match(script, /MouseEvent\('click'/);
  assert.match(script, /classList\.remove\('er-subscribe-source-modal-hidden'\)/);
  assert.doesNotMatch(script, /node\.remove\(\)/);
  assert.match(script, /\.n-list-item/);
  assert.match(script, /\.cursor-pointer/);
  assert.match(script, /\/api\/v1\/user\/getSubscribe/);
  assert.match(script, /\/api\/v1\/user\/server\/fetch/);
  assert.match(script, /VUE_NAIVE_ACCESS_TOKEN/);
  assert.match(script, /navigator\.clipboard\.writeText/);
  assert.match(script, /copyTextWithExecCommand/);
  assert.match(script, /\.catch\(function \(\) \{\s*return copyTextWithExecCommand\(text\);/);
  assert.match(script, /document\.execCommand\('copy'\)/);
});

test('ElephantRoute dashboard hides Surge when the subscription has only VLESS nodes', () => {
  const script = readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.js');

  assert.match(script, /loadNodeCompatibility/);
  assert.match(script, /hideUnsupportedSurgeOption/);
  assert.match(script, /data-er-surge-hidden/);
  assert.match(script, /surgeCompatible === 0/);
  assert.doesNotMatch(script, /SURGE_VLESS_WARNING/);
  assert.doesNotMatch(script, /建议使用 SingBox、Hiddify 或 Clash Meta/);
});

test('ElephantRoute dashboard injects Karing after Hiddify in the one-click subscribe modal', () => {
  const script = readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.js');

  assert.match(script, /enhanceKaringSubscribeOption/);
  assert.match(script, /findSubscribeListItem/);
  assert.match(script, /createKaringSubscribeItem/);
  assert.match(script, /openKaringImport/);
  assert.match(script, /karing:\/\/install-config\?url=/);
  assert.match(script, /images\/karing\.png/);
  assert.match(script, /findSubscribeListItem\('Hiddify'\)[\s\S]*insertAdjacentElement\('afterend', karingItem\)/);
  assert.match(script, /insertAdjacentElement\('afterend', karingItem\)/);
});

test('ElephantRoute dashboard injects Clash Mi after copy subscription on Apple mobile', () => {
  const script = readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.js');
  const stylesheet = readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.css');

  assert.match(script, /CLASH_MI_ICON_PATH = 'images\/clash-mi\.png'/);
  assert.match(script, /function isAppleMobilePlatform\(\)/);
  assert.match(script, /iPhone\|iPad\|iPod/);
  assert.match(script, /platform === 'MacIntel' && navigator\.maxTouchPoints > 1/);
  assert.match(script, /function buildClashMiImportUrl\(subscribeUrl\)/);
  assert.match(script, /clashmi:\/\/install-config\?url=/);
  assert.doesNotMatch(script, /clash:\/\/install-config\?url=/);
  assert.match(script, /function openClashMiImport\(\)/);
  assert.match(script, /function enhanceClashMiSubscribeOption\(\)/);
  assert.match(script, /findSubscribeListItem\('复制订阅地址'\) \|\| findSubscribeListItem\('复制订阅链接'\)/);
  assert.match(script, /copyItem\.insertAdjacentElement\('afterend', clashMiItem\)/);
  assert.match(script, /setAttribute\('aria-label', '导入到 Clash Mi'\)/);
  assert.match(script, /replaceText\(clashMiItem, getNodeText\(clashMiItem\), '导入到 Clash Mi'\)/);
  assert.match(script, /getThemeAssetUrl\(CLASH_MI_ICON_PATH\)/);
  assert.match(script, /enhanceKaringSubscribeOption\(\);[\s\S]*enhanceClashMiSubscribeOption\(\);[\s\S]*enhanceDesktopClashVergeSubscribeOption\(\);/);
  assert.match(stylesheet, /\.er-clash-mi-subscribe-icon/);
  assert.match(stylesheet, /\.er-clash-mi-subscribe-icon\s*\{[\s\S]*width: 42px;[\s\S]*height: 42px;[\s\S]*flex: 0 0 42px;/);
  assert.match(stylesheet, /\.er-clash-mi-subscribe-icon\s*\{[\s\S]*object-fit: contain;/);
});

test('ElephantRoute dashboard normalizes desktop Clash entries to Clash Verge', () => {
  const script = readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.js');
  const stylesheet = readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.css');

  assert.match(script, /CLASH_VERGE_ICON_PATH = 'images\/clash-verge\.png'/);
  assert.match(script, /function isDesktopPlatform\(\)/);
  assert.match(script, /Android/i);
  assert.match(script, /return isMacLike \|\| isWindowsLike/);
  assert.match(script, /function enhanceDesktopClashVergeSubscribeOption\(\)/);
  assert.match(script, /findSubscribeListItem\('Clash Meta'\) \|\| findSubscribeListItem\('Clash'\)/);
  assert.match(script, /replaceText\(clashItem, 'Clash Meta', 'Clash Verge'\)/);
  assert.match(script, /replaceText\(clashItem, 'Clash', 'Clash Verge'\)/);
  assert.match(script, /aria-label', '导入到 Clash Verge'/);
  assert.match(script, /getThemeAssetUrl\(CLASH_VERGE_ICON_PATH\)/);
  assert.match(script, /enhanceKaringSubscribeOption\(\);[\s\S]*enhanceDesktopClashVergeSubscribeOption\(\);/);
  assert.match(stylesheet, /\.er-clash-verge-subscribe-icon/);
});

test('ElephantRoute dashboard normalizes shortcut menu labels and order', () => {
  const script = readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.js');

  assert.match(script, /PROBLEM_APPEAL_LABEL = '提交问题申诉'/);
  assert.match(script, /PROBLEM_APPEAL_DESCRIPTION = '提交问题后将在3小时内回复'/);
  assert.match(script, /PROBLEM_APPEAL_TITLE_COLOR = '#e53e3e'/);
  assert.match(script, /DIFY_CONTEXT_API_PATH = '\/api\/v1\/user\/support\/dify-context'/);
  assert.match(script, /DIFY_OPEN_QUERY_KEY = 'open_ai_support'/);
  assert.match(script, /removeRenewShortcut/);
  assert.match(script, /normalizeProblemAppealShortcut/);
  assert.match(script, /applyProblemAppealTitleColor/);
  assert.match(script, /replaceProblemAppealIcon/);
  assert.match(script, /bindProblemAppealAiSupport/);
  assert.match(script, /openDifySupportChat/);
  assert.match(script, /preloadDifySupportBubble/);
  assert.match(script, /waitForDifyBubbleButton/);
  assert.match(script, /dynamicScript: true/);
  assert.match(script, /#dify-chatbot-bubble-button\{[^}]*left:1rem!important;right:auto!important/);
  assert.match(script, /#dify-chatbot-bubble-window\{[^}]*left:1rem!important;right:auto!important/);
  assert.doesNotMatch(script, /xboard_context_token/);
  assert.doesNotMatch(script, /context\.context_token/);
  assert.match(script, /moveProblemAppealAfterSubscribe/);
  assert.match(script, /findDashboardShortcut\('续费订阅'\)[\s\S]*renewItem\.remove\(\)/);
  assert.match(script, /findDashboardShortcut\('一键订阅'\)[\s\S]*insertBefore\(problemItem, subscribeItem\.nextSibling\)/);
  assert.match(script, /openDifySupportChat\(\)/);
  assert.match(script, /preloadDifySupportBubble\(\);[\s\S]*maybeAutoOpenDifySupport\(\);/);
  assert.doesNotMatch(script, /window\.location\.href = AI_SUPPORT_URL/);
  assert.match(script, /document\.getElementById\('dify-chatbot-bubble-button'\)/);
  assert.match(script, /existingClassName[\s\S]*createProblemAppealIcon\(existingClassName\)/);
  assert.match(script, /<path d="M4 12a8 8 0 0 1 16 0">/);
  assert.match(script, /<path d="M18 19c0 1\.1-1\.8 2-4 2h-2">/);
});

test('AI support compatibility page redirects into the app and no longer renders a middle handoff UI', () => {
  const route = readRepoFile('routes/web.php');
  const view = readRepoFile('resources/views/support_ai.blade.php');

  assert.match(route, /Route::get\('\/support\/ai'/);
  assert.match(view, /open_ai_support=1/);
  assert.match(view, /window\.location\.replace/);
  assert.doesNotMatch(view, /打开 AI 客服/);
  assert.doesNotMatch(view, /转人工/);
  assert.doesNotMatch(view, /window\.difyChatbotConfig/);
  assert.doesNotMatch(view, /dify-chatbot-bubble-button/);
});

test('ElephantRoute sidebar permanently hides traffic detail menu item', () => {
  const script = readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.js');

  assert.match(script, /HIDDEN_SIDEBAR_MENU_LABELS = \['流量明细'\]/);
  assert.match(script, /findTextElements/);
  assert.match(script, /removeHiddenSidebarMenuItems/);
  assert.match(script, /closestSidebarMenuItem/);
  assert.match(script, /\.n-layout-sider/);
  assert.match(script, /closest\('\[role="menuitem"\], \.n-menu-item, a, button, \.cursor-pointer'\)/);
  assert.match(script, /findTextElements\(label\)\.forEach/);
  assert.match(script, /item\.remove\(\)/);
  assert.match(script, /removeHiddenSidebarMenuItems\(\);[\s\S]*var applied = applyDownloadEntry/);
  assert.match(script, /removeHiddenSidebarMenuItems\(\);[\s\S]*if \(isDashboardRoute\(\)\) scheduleDownloadEntry\(\)/);
});

test('ElephantRoute rewrites ticket page copy to appeal wording', () => {
  const script = readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.js');

  assert.match(script, /TICKET_APPEAL_TEXT_REPLACEMENTS/);
  assert.match(script, /TICKET_APPEAL_EXACT_TEXT_REPLACEMENTS/);
  assert.match(script, /\['我的工单', '问题申诉'\]/);
  assert.match(script, /\['工单历史', '申诉记录'\]/);
  assert.match(script, /\['新的工单', '提交申诉'\]/);
  assert.match(script, /\['请输入工单主题', '请输入申诉主题'\]/);
  assert.match(script, /\['工单级别', '申诉级别'\]/);
  assert.match(script, /\['工单等级', '申诉级别'\]/);
  assert.match(script, /\['请选择工单优先级', '请选择申诉优先级'\]/);
  assert.match(script, /\['请选择工单等级', '请选择申诉优先级'\]/);
  assert.match(script, /\['请描述您遇到的问题', '请描述您的申诉问题'\]/);
  assert.match(script, /\['工单详情', '申诉详情'\]/);
  assert.match(script, /\['工单状态', '申诉状态'\]/);
  assert.match(script, /TICKET_APPEAL_EXACT_TEXT_REPLACEMENTS = \[\s*\['主题', '申诉主题'\],\s*\['消息', '申诉内容'\]\s*\]/);
  assert.match(script, /replaceExactAppealText/);
  assert.match(script, /var normalized = value\.trim\(\)/);
  assert.match(script, /if \(normalized === pair\[0\]\)/);
  assert.match(script, /normalizeTicketAppealWording/);
  assert.match(script, /replaceAppealTextInNode/);
  assert.match(script, /replaceAppealTextAttributes/);
  assert.match(script, /placeholder/);
  assert.match(script, /var nextTitle = replaceAppealText\(document\.title\)/);
  assert.match(script, /if \(nextTitle !== document\.title\) document\.title = nextTitle/);
  assert.match(script, /normalizeTicketAppealWording\(\);[\s\S]*removeHiddenSidebarMenuItems\(\);[\s\S]*var applied = applyDownloadEntry/);
  assert.match(script, /normalizeTicketAppealWording\(\);[\s\S]*removeHiddenSidebarMenuItems\(\);[\s\S]*if \(isDashboardRoute\(\)\) scheduleDownloadEntry\(\)/);
});

test('subscription import deeplinks request deterministic Surge format', () => {
  const elephantBundle = readRepoFile('theme/ElephantRoute/assets/umi.js');
  const xboardBundle = readRepoFile('theme/Xboard/assets/umi.js');
  const v2boardBundle = readRepoFile('theme/v2board/assets/umi.js');

  assert.match(elephantBundle, /appendSubscribeFlag\(G,"surge"\)/);
  assert.match(xboardBundle, /appendSubscribeFlag\(G,"surge"\)/);
  assert.match(v2boardBundle, /appendSubscribeFlag\(e,"surge"\)/);
});

test('ElephantRoute user bundle supports every configured theme color option', () => {
  const config = JSON.parse(readRepoFile('theme/ElephantRoute/config.json'));
  const themeColorConfig = config.configs.find((item) => item.field_name === 'theme_color');
  const bundle = readRepoFile('theme/ElephantRoute/assets/umi.js');
  const sectionStart = bundle.indexOf('function WQ()');
  const sectionEnd = bundle.indexOf('const qQ=', sectionStart);

  assert.ok(themeColorConfig, 'theme_color config is declared');
  assert.notEqual(sectionStart, -1, 'theme selector function exists');
  assert.notEqual(sectionEnd, -1, 'theme selector constants exist');

  const selectorSection = bundle.slice(sectionStart, sectionEnd);
  for (const option of Object.keys(themeColorConfig.select_options)) {
    assert.match(selectorSection, new RegExp(`(?:^|[,{])${option}:`));
  }
});

test('ElephantRoute user bundle hides user-facing rate columns', () => {
  const bundle = readRepoFile('theme/ElephantRoute/assets/umi.js');

  assert.doesNotMatch(bundle, /X\("div",\$Fe,\[nt\(pe\(r\.\$t\("倍率"\)\)/);
  assert.doesNotMatch(bundle, /default:ve\(\(\)=>\[nt\(pe\(m\.rate\)\+" x ",1\)\]\),_:2\},1024\)/);
  assert.doesNotMatch(bundle, /\{title:t\("扣费倍率"\),key:"server_rate"/);
  assert.doesNotMatch(bundle, /default:\(\)=>t\("公式：\(实际上行 \+ 实际下行\) x 扣费倍率 = 扣除流量"\)/);
  assert.match(bundle, /"\/src\/views\/traffic\/route\.ts":TR/);
});

test('ElephantRoute subscription shortcuts have responsive layout styles', () => {
  const stylesheet = readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.css');

  assert.match(stylesheet, /\.er-subscribe-layout/);
  assert.match(stylesheet, /\.er-subscribe-action-panel/);
  assert.match(stylesheet, /\.er-subscribe-action-button/);
  assert.match(stylesheet, /\.er-dashboard-support-icon/);
  assert.match(stylesheet, /background: #fff1f1/);
  assert.match(stylesheet, /\.er-subscribe-source-menu-hidden/);
  assert.match(stylesheet, /\.er-subscribe-source-modal-hidden/);
  assert.doesNotMatch(stylesheet, /\.er-surge-vless-warning/);
  assert.match(stylesheet, /@media \(max-width: 767px\)/);
});

test('ElephantRoute dashboard asset cache busting is updated for subscription shortcuts', () => {
  const blade = readRepoFile('theme/ElephantRoute/dashboard.blade.php');

  assert.match(blade, /elephant-route-dashboard\.css\?v=\{\{\$version\}\}-er20260618clashMi2/);
  assert.match(blade, /elephant-route-dashboard\.js\?v=\{\{\$version\}\}-er20260618clashMi2/);
});
