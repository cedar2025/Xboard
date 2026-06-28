const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

test('macOS beta build defaults to the production domain and architecture-specific outputs', () => {
  const script = readRepoFile('clients/elephant-route-deprecated/build_macos_beta.sh');

  assert.match(script, /BASE_URL="\$\{BASE_URL:-https:\/\/www\.elephant223\.com\}"/);
  assert.match(script, /APP_DISTRIBUTION_URL="\$\{APP_DISTRIBUTION_URL:-\$\{BASE_URL\}\}"/);
  assert.match(script, /MACOS_ARCH="\$\{MACOS_ARCH:-arm64\}"/);
  assert.match(script, /case "\$\{MACOS_ARCH\}" in/);
  assert.match(script, /\$\{APP_NAME\}-macos-\$\{MACOS_ARCH\}\.app/);
  assert.match(script, /\$\{APP_NAME\}-macos-\$\{MACOS_ARCH\}\.dmg/);
  assert.match(script, /prune_for_arch/);
  assert.match(script, /thin_macho_file/);
  assert.match(script, /--dart-define=BASE_URL="\$\{BASE_URL\}"/);
  assert.match(script, /--dart-define=APP_DISTRIBUTION_URL="\$\{APP_DISTRIBUTION_URL\}"/);
});

test('macOS package excludes non-target binaries and bundled CJK fonts', () => {
  const pubspec = readRepoFile('clients/elephant-route-deprecated/pubspec.yaml');
  const app = readRepoFile('clients/elephant-route-deprecated/lib/main.dart');

  assert.doesNotMatch(pubspec, /assets\/bin\/windows\//);
  assert.doesNotMatch(pubspec, /SourceHanSansCN-Regular\.otf/);
  assert.doesNotMatch(pubspec, /SourceHanSansCN-Bold\.otf/);
  assert.doesNotMatch(app, /fontFamily:\s*'Source Han Sans CN'/);
});

test('macOS TUN helper build honors MACOS_ARCH for split packages', () => {
  const helperScript = readRepoFile('clients/elephant-route-deprecated/macos/build_tun_helper.sh');

  assert.match(helperScript, /MACOS_ARCH/);
  assert.match(helperScript, /x64\|x86_64\|amd64\)/);
  assert.match(helperScript, /x86_64-apple-macos13\.0/);
  assert.match(helperScript, /arm64-apple-macos13\.0/);
});
