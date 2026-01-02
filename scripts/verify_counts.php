<?php
/**
 * Verify row counts between sqlite and mysql for key tables.
 * Usage: php scripts/verify_counts.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$db = $app->make('db');
$mysql = $db->connection('mysql');

$sqlitePath = base_path('database/database.sqlite');
if (!file_exists($sqlitePath)) {
    echo "SQLite file not found at $sqlitePath\n";
    exit(1);
}

try {
    $sqlitePdo = new PDO('sqlite:' . $sqlitePath);
    $sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    echo "Failed to open sqlite: " . $e->getMessage() . "\n";
    exit(1);
}

$tables = [
    'v2_user',
    'v2_plan',
    'v2_order',
    'v2_stat_user',
    'v2_plugins',
];

echo "Verifying table row counts (sqlite -> mysql)\n";
echo str_repeat('=', 60) . "\n";

foreach ($tables as $table) {
    // sqlite count
    try {
        $stmt = $sqlitePdo->prepare("SELECT COUNT(*) as c FROM \"$table\"");
        $stmt->execute();
        $srow = $stmt->fetch(PDO::FETCH_ASSOC);
        $scount = (int)($srow['c'] ?? 0);
    } catch (Exception $e) {
        $scount = null;
    }

    // mysql count
    try {
        $mrow = $mysql->selectOne("SELECT COUNT(*) as c FROM `$table`");
        $mcount = (int)($mrow->c ?? 0);
    } catch (Exception $e) {
        $mcount = null;
    }

    $diff = null;
    if (is_int($scount) && is_int($mcount)) {
        $diff = $mcount - $scount;
    }

    printf("%-20s sqlite=%8s  mysql=%8s  diff=%+6s\n", $table, $scount === null ? 'N/A' : $scount, $mcount === null ? 'N/A' : $mcount, $diff === null ? 'N/A' : $diff);
}

echo str_repeat('=', 60) . "\n";
echo "Done.\n";
