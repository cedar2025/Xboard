<?php
/**
 * Copy data from the `sqlite` connection to the `mysql` connection.
 *
 * Usage: php scripts/sqlite_to_mysql.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// resolve mysql connection from Laravel
$db = $app->make('db');
$mysql = $db->connection('mysql');

echo "Start sqlite -> mysql migration\n";

// open sqlite file directly via PDO to avoid config/env confusion
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

// helper to run query and fetch all assoc
function fetchAllAssoc(PDO $pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// get list of tables in sqlite (exclude sqlite internal tables)
$tables = fetchAllAssoc($sqlitePdo, "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
$tables = array_map(function($r){ return $r['name'] ?? null; }, $tables);
$tables = array_filter($tables);

$exclude = [
    'migrations', // optional: skip Laravel migrations table if you don't want to copy it
];

foreach ($tables as $table) {
    if (in_array($table, $exclude, true)) {
        echo "Skipping table: $table\n";
        continue;
    }

    echo "Processing table: $table\n";

    try {
        // disable foreign key checks on mysql for safe truncate/insert
        $mysql->statement('SET FOREIGN_KEY_CHECKS=0');

        // truncate destination table to avoid duplicates
        $mysql->table($table)->truncate();

        $chunk = 500;
        $offset = 0;

        while (true) {
            $rows = fetchAllAssoc($sqlitePdo, "SELECT * FROM \"$table\" LIMIT :limit OFFSET :offset", [':limit' => $chunk, ':offset' => $offset]);
            if (empty($rows)) {
                break;
            }

            // insert into mysql in DB transaction to reduce partial states
            $mysql->beginTransaction();
            try {
                foreach (array_chunk($rows, 100) as $part) {
                    $mysql->table($table)->insert($part);
                }
                $mysql->commit();
            } catch (Exception $e) {
                $mysql->rollBack();
                throw $e;
            }

            $offset += count($rows);
            echo "  Inserted $offset rows into $table...\n";
        }

        $mysql->statement('SET FOREIGN_KEY_CHECKS=1');

        echo "Finished table: $table\n";

    } catch (Exception $e) {
        echo "Error migrating table $table: " . $e->getMessage() . "\n";
        // re-enable foreign key checks if something went wrong
        try { $mysql->statement('SET FOREIGN_KEY_CHECKS=1'); } catch (Exception $_) {}
    }
}

echo "Migration completed.\n";
