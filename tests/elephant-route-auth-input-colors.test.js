const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

const authCssPaths = [
  'theme/ElephantRoute/assets/elephant-route-auth.css',
  'public/theme/ElephantRoute/assets/elephant-route-auth.css'
];

test('ElephantRoute auth input colors stay readable on light inputs in dark system mode', () => {
  for (const cssPath of authCssPaths) {
    const css = readRepoFile(cssPath);

    assert.match(css, /body\.er-auth-page \.n-input \{[\s\S]*--n-text-color: #141512 !important;/, `${cssPath} should force dark input text`);
    assert.match(css, /body\.er-auth-page \.n-input \{[\s\S]*--n-placeholder-color: rgba\(20, 21, 18, 0\.46\) !important;/, `${cssPath} should force readable placeholder text`);
    assert.match(css, /body\.er-auth-page \.n-input \{[\s\S]*--n-caret-color: #141512 !important;/, `${cssPath} should force a visible caret`);
    assert.match(css, /body\.er-auth-page \.n-input \{[\s\S]*--n-icon-color: rgba\(20, 21, 18, 0\.42\) !important;/, `${cssPath} should force readable input icons`);
    assert.match(css, /body\.er-auth-page \.n-input__input-el \{[\s\S]*color: #141512 !important;[\s\S]*caret-color: #141512 !important;/, `${cssPath} should force native input text and caret colors`);
  }
});

test('ElephantRoute auth override assets are synced to the public theme directory', () => {
  assert.equal(
    readRepoFile('public/theme/ElephantRoute/assets/elephant-route-auth.css'),
    readRepoFile('theme/ElephantRoute/assets/elephant-route-auth.css')
  );
});
