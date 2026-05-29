const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

function getProtocolFlags(relativePath) {
  const source = readRepoFile(relativePath);
  const match = source.match(/public \$flags = \[([^\]]*)\]/);
  assert.ok(match, `${relativePath} declares protocol flags`);

  return Array.from(match[1].matchAll(/'([^']+)'/g), (flag) => flag[1]);
}

function matchProtocolFile(flag) {
  const protocolsDir = path.join(repoRoot, 'app/Protocols');
  const protocolFiles = fs.readdirSync(protocolsDir)
    .filter((file) => file.endsWith('.php'))
    .sort()
    .map((file) => `app/Protocols/${file}`)
    .reverse();

  return protocolFiles.find((relativePath) => {
    return getProtocolFlags(relativePath).some((protocolFlag) => {
      return flag.toLowerCase().includes(protocolFlag.toLowerCase());
    });
  });
}

test('Karing user agents are routed to the sing-box subscription renderer', () => {
  const singBoxFlags = getProtocolFlags('app/Protocols/SingBox.php');
  const generalFlags = getProtocolFlags('app/Protocols/General.php');

  assert.deepEqual(matchProtocolFile('karing/1.2.18.2102 ios'), 'app/Protocols/SingBox.php');
  assert.ok(singBoxFlags.includes('karing'));
  assert.ok(singBoxFlags.includes('singbox'));
  assert.equal(generalFlags.includes('karing'), false);
});
