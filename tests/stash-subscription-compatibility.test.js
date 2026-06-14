const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const test = require('node:test');
const assert = require('node:assert/strict');

const repoRoot = path.resolve(__dirname, '..');

function readRepoFile(relativePath) {
  return fs.readFileSync(path.join(repoRoot, relativePath), 'utf8');
}

function buildStashVless(protocolSettings) {
  const phpScript = `
    require "vendor/autoload.php";

    $protocolSettings = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $server = [
        "name" => "vless-tcp-node",
        "type" => "vless",
        "host" => "example.com",
        "port" => 443,
        "password" => "00000000-0000-0000-0000-000000000000",
        "protocol_settings" => $protocolSettings,
    ];

    $reflection = new ReflectionClass(App\\Protocols\\Stash::class);
    $stash = $reflection->newInstanceWithoutConstructor();
    $proxy = $stash->buildVless("00000000-0000-0000-0000-000000000000", $server);

    echo json_encode($proxy, JSON_THROW_ON_ERROR);
  `;

  const result = spawnSync(
    'php',
    ['-r', phpScript, Buffer.from(JSON.stringify(protocolSettings)).toString('base64')],
    {
      cwd: repoRoot,
      encoding: 'utf8',
    }
  );

  assert.equal(result.status, 0, result.stderr || result.stdout);
  return JSON.parse(result.stdout);
}

test('Stash VLESS tcp http header renders network as a string', () => {
  const proxy = buildStashVless({
    tls: 0,
    network: 'tcp',
    network_settings: {
      header: {
        type: 'http',
        request: {
          headers: {
            Host: ['example.com'],
          },
          path: ['/'],
        },
      },
    },
  });

  assert.equal(proxy.network, 'http');
  assert.equal(typeof proxy.network, 'string');
  assert.deepEqual(proxy['http-opts'], {
    headers: {
      Host: ['example.com'],
    },
    path: ['/'],
  });
});

test('Stash VLESS default tcp header does not render boolean network', () => {
  const explicitTcpProxy = buildStashVless({
    tls: 0,
    network: 'tcp',
    network_settings: {
      header: {
        type: 'tcp',
      },
    },
  });

  const defaultTcpProxy = buildStashVless({
    tls: 0,
    network: 'tcp',
    network_settings: {},
  });

  assert.notEqual(typeof explicitTcpProxy.network, 'boolean');
  assert.equal(Object.hasOwn(explicitTcpProxy, 'network'), false);
  assert.notEqual(typeof defaultTcpProxy.network, 'boolean');
  assert.equal(Object.hasOwn(defaultTcpProxy, 'network'), false);
});

test('Stash VLESS tcp network assignment is not mixed with comparison', () => {
  const source = readRepoFile('app/Protocols/Stash.php');

  assert.doesNotMatch(
    source,
    /if\s*\(\s*\$headerType\s*=\s*data_get\([^)]*\)\s*!=\s*'tcp'\s*\)/
  );
});
