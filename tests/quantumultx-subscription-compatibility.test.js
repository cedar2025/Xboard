const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
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

function runPhp(script, args = []) {
  const result = spawnSync('php', ['-r', script, ...args], {
    cwd: repoRoot,
    encoding: 'utf8',
  });

  assert.equal(result.status, 0, result.stderr || result.stdout);
  return result.stdout;
}

function inspectQuantumultX() {
  const phpScript = `
    require "vendor/autoload.php";

    $reflection = new ReflectionClass("App\\\\Protocols\\\\QuantumultX");
    $instance = $reflection->newInstanceWithoutConstructor();
    $allowed = new ReflectionProperty("App\\\\Protocols\\\\QuantumultX", "allowedProtocols");

    echo json_encode([
        "allowed" => $allowed->getValue($instance),
        "hasBuildVless" => method_exists("App\\\\Protocols\\\\QuantumultX", "buildVless"),
    ], JSON_THROW_ON_ERROR);
  `;

  return JSON.parse(runPhp(phpScript));
}

function buildQuantumultXVless(protocolSettings) {
  const phpScript = `
    require "vendor/autoload.php";

    $protocolSettings = json_decode(base64_decode($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $server = [
        "name" => "vless-node",
        "type" => "vless",
        "host" => "example.com",
        "port" => 443,
        "password" => "00000000-0000-0000-0000-000000000000",
        "protocol_settings" => $protocolSettings,
    ];

    $line = App\\Protocols\\QuantumultX::buildVless("00000000-0000-0000-0000-000000000000", $server);

    echo $line;
  `;

  return runPhp(phpScript, [
    Buffer.from(JSON.stringify(protocolSettings)).toString('base64'),
  ]);
}

test('common QuantumultX user agents are routed to the QuantumultX renderer', () => {
  assert.equal(matchProtocolFile('quantumultx/1.5.1'), 'app/Protocols/QuantumultX.php');
  assert.equal(matchProtocolFile('quantumult x/1.5.1'), 'app/Protocols/QuantumultX.php');
  assert.equal(matchProtocolFile('quantumult-x/1.5.1'), 'app/Protocols/QuantumultX.php');
});

test('QuantumultX renderer allows VLESS and exposes a VLESS builder', () => {
  const info = inspectQuantumultX();

  assert.ok(info.allowed.includes('vless'));
  assert.equal(info.hasBuildVless, true);
});

test('QuantumultX VLESS tcp http header renders native obfs fields', () => {
  const line = buildQuantumultXVless({
    tls: 0,
    flow: null,
    network: 'tcp',
    network_settings: {
      header: {
        type: 'http',
        request: {
          headers: {
            Host: ['example.com'],
          },
          path: ['/resource/file'],
        },
      },
    },
  });

  assert.match(line, /^vless=example\.com:443,/);
  assert.match(line, /method=none/);
  assert.match(line, /password=00000000-0000-0000-0000-000000000000/);
  assert.match(line, /obfs=http/);
  assert.match(line, /obfs-host=example\.com/);
  assert.match(line, /obfs-uri=\/resource\/file/);
  assert.match(line, /udp-relay=true/);
  assert.match(line, /tag=vless-node/);
});

test('QuantumultX VLESS websocket tls renders wss and tls verification fields', () => {
  const line = buildQuantumultXVless({
    tls: 1,
    flow: null,
    network: 'ws',
    tls_settings: {
      allow_insecure: true,
      server_name: 'tls.example.com',
    },
    network_settings: {
      path: '/ws',
      headers: {
        Host: 'ws.example.com',
      },
    },
  });

  assert.match(line, /obfs=wss/);
  assert.match(line, /obfs-host=tls\.example\.com/);
  assert.match(line, /obfs-uri=\/ws/);
  assert.match(line, /tls-verification=false/);
});

test('QuantumultX VLESS reality vision renders reality fields and disables fast open', () => {
  const line = buildQuantumultXVless({
    tls: 2,
    flow: 'xtls-rprx-vision',
    network: 'tcp',
    reality_settings: {
      server_name: 'apple.com',
      public_key: 'reality-public-key',
      short_id: '0123456789abcdef',
    },
  });

  assert.match(line, /obfs=over-tls/);
  assert.match(line, /obfs-host=apple\.com/);
  assert.match(line, /reality-base64-pubkey=reality-public-key/);
  assert.match(line, /reality-hex-shortid=0123456789abcdef/);
  assert.match(line, /vless-flow=xtls-rprx-vision/);
  assert.match(line, /fast-open=false/);
  assert.doesNotMatch(line, /fast-open=true/);
});
