const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

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

test('ElephantRoute subscription shortcuts have responsive layout styles', () => {
  const stylesheet = readRepoFile('theme/ElephantRoute/assets/elephant-route-dashboard.css');

  assert.match(stylesheet, /\.er-subscribe-layout/);
  assert.match(stylesheet, /\.er-subscribe-action-panel/);
  assert.match(stylesheet, /\.er-subscribe-action-button/);
  assert.match(stylesheet, /background: #fff1f1/);
  assert.match(stylesheet, /\.er-subscribe-source-menu-hidden/);
  assert.match(stylesheet, /\.er-subscribe-source-modal-hidden/);
  assert.doesNotMatch(stylesheet, /\.er-surge-vless-warning/);
  assert.match(stylesheet, /@media \(max-width: 767px\)/);
});

test('ElephantRoute dashboard asset cache busting is updated for subscription shortcuts', () => {
  const blade = readRepoFile('theme/ElephantRoute/dashboard.blade.php');

  assert.match(blade, /elephant-route-dashboard\.css\?v=\{\{\$version\}\}-er20260530surgehide1/);
  assert.match(blade, /elephant-route-dashboard\.js\?v=\{\{\$version\}\}-er20260530surgehide1/);
});
