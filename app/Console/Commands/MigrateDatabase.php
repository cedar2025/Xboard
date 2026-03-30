<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Doctrine\DBAL\Schema\Column;

class MigrateDatabase extends Command
{
    /**
     * The name and signature of the console command.
     * 添加了 --target 选项来指定目标数据库类型
     *
     * @var string
     */
    protected $signature = 'migrate:sqlite-to-db {--target=mysql : The target database type (mysql, pgsql)} {--force : Force the operation to run when in production}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Interactively migrate all data from SQLite to a specified database (MySQL, PostgreSQL).';

    /**
     * The original .env content.
     * @var string
     */
    protected $originalEnvContent;

    /**
     * The chosen target database configuration.
     * @var array
     */
    protected $targetDbConfig;

    /**
     * Supported database configurations.
     * 添加新数据库支持，只需在这里添加配置即可
     * @var array
     */
    protected $supportedDatabases = [
        'mysql' => [
            'name' => 'MySQL / MariaDB',
            'driver' => 'mysql',
            'default_port' => '3306',
            'env_keys' => [
                'connection' => 'DB_CONNECTION',
                'host' => 'DB_HOST',
                'port' => 'DB_PORT',
                'database' => 'DB_DATABASE',
                'username' => 'DB_USERNAME',
                'password' => 'DB_PASSWORD',
            ],
            'default_values' => [
                'integer' => 0, 'bigint' => 0, 'smallint' => 0, 'decimal' => 0, 'float' => 0,
                'string' => '', 'text' => '', 'guid' => '',
                'boolean' => 0, // MySQL uses TINYINT(1)
                'datetime' => '1970-01-01 00:00:00', 'date' => '1970-01-01', 'timestamp' => '1970-01-01 00:00:00',
            ],
        ],
        'pgsql' => [
            'name' => 'PostgreSQL',
            'driver' => 'pgsql',
            'default_port' => '5432',
            'env_keys' => [
                'connection' => 'DB_CONNECTION',
                'host' => 'DB_HOST',
                'port' => 'DB_PORT',
                'database' => 'DB_DATABASE',
                'username' => 'DB_USERNAME',
                'password' => 'DB_PASSWORD',
            ],
            'default_values' => [
                'integer' => 0, 'bigint' => 0, 'smallint' => 0, 'decimal' => 0, 'float' => 0,
                'string' => '', 'text' => '', 'guid' => '',
                'boolean' => false, // PostgreSQL uses native boolean
                'datetime' => '1970-01-01 00:00:00', 'date' => '1970-01-01', 'timestamp' => '1970-01-01 00:00:00',
            ],
        ],
    ];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        if (!class_exists('Doctrine\DBAL\Schema\Column')) {
            $this->error('The "doctrine/dbal" package is required. Please run: composer require doctrine/dbal');
            return 1;
        }

        if ($this->laravel->environment() === 'production' && !$this->option('force')) {
            $this->error('This command cannot be run in production environment without --force flag.');
            return 1;
        }

        $this->info('🚀 Starting robust SQLite to Database migration tool...');
        $this->warn('Please ensure your target database server is running and you have created an empty database.');

        // 1. 确定目标数据库类型
        $this->determineTargetDatabase();

        // 2. 备份并更新 .env 文件
        $this->backupAndUpdateEnv();

        // 3. 清除配置缓存
        $this->info('Clearing configuration cache...');
        Artisan::call('config:clear');

        // 4. 运行数据库结构迁移
        $this->info("Running database migrations to create tables in {$this->targetDbConfig['name']}...");
        Artisan::call('migrate:fresh', ['--force' => true]);

        // 5. 执行通用数据迁移
        $this->performDataMigration();

        // 6. 完成
        $this->info("✅ Data migration to {$this->targetDbConfig['name']} completed successfully!");
        $this->warn('Please restart your web server and PHP-FPM to apply the new database configuration.');
        $this->info('Example commands to restart services (may vary by system):');
        $this->line('  sudo systemctl restart nginx');
        $this->line('  sudo systemctl restart php8.1-fpm'); // Use your PHP version

        return 0;
    }

    /**
     * 确定目标数据库类型
     */
    private function determineTargetDatabase()
    {
        $target = $this->option('target');
        if (!isset($this->supportedDatabases[$target])) {
            $choices = array_column($this->supportedDatabases, 'name', 'driver');
            $chosenDriver = $this->choice('Which database do you want to migrate to?', $choices, 'mysql');
            $target = array_search($chosenDriver, $choices);
        }
        $this->targetDbConfig = $this->supportedDatabases[$target];
        $this->info("Target database selected: {$this->targetDbConfig['name']}");
    }

    /**
     * 备份原始 .env 并更新数据库配置
     */
    private function backupAndUpdateEnv()
    {
        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            // If .env does not exist, copy from .env.example
            if (File::exists(base_path('.env.example'))) {
                File::copy(base_path('.env.example'), $envPath);
                $this->info('.env file not found. Created one from .env.example.');
            } else {
                $this->error('.env file not found, and no .env.example exists to copy from.');
                return;
            }
        }
        
        $this->originalEnvContent = File::get($envPath);
        $backupPath = base_path('.env.backup.' . date('YmdHis'));
        File::put($backupPath, $this->originalEnvContent);
        $this->info("Original .env backed up to: {$backupPath}");

        $this->info("Please provide your {$this->targetDbConfig['name']} connection details:");

        $keys = $this->targetDbConfig['env_keys'];
        $host = $this->ask('Host', '127.0.0.1');
        $port = $this->ask('Port', $this->targetDbConfig['default_port']);
        $database = $this->ask('Database Name');
        $username = $this->ask('Username');
        $password = $this->secret('Password');

        $newEnvContent = Str::of($this->originalEnvContent)
            ->replaceMatches("/^{$keys['connection']}=.*/m", "{$keys['connection']}={$this->targetDbConfig['driver']}")
            ->replaceMatches("/^{$keys['host']}=.*/m", "{$keys['host']}={$host}")
            ->replaceMatches("/^{$keys['port']}=.*/m", "{$keys['port']}={$port}")
            ->replaceMatches("/^{$keys['database']}=.*/m", "{$keys['database']}={$database}")
            ->replaceMatches("/^{$keys['username']}=.*/m", "{$keys['username']}={$username}")
            ->replaceMatches("/^{$keys['password']}=.*/m", "{$keys['password']}={$password}");

        File::put($envPath, $newEnvContent);
        $this->info('.env file has been updated.');
    }

    /**
     * 执行通用数据迁移
     */
    private function performDataMigration()
    {
        // Set up the source SQLite connection dynamically
        $sqlitePath = '';
        if (config('database.connections.sqlite')) {
            $sqlitePath = config('database.connections.sqlite.database');
        }
        
        if ($sqlitePath == ':memory:' || empty($sqlitePath) || !File::exists($sqlitePath)) {
             $sqlitePath = $this->ask('Please provide the path to the source SQLite database file');
        }

        if (!File::exists($sqlitePath)) {
            $this->error("SQLite file not found at: {$sqlitePath}");
            return;
        }

        config(['database.connections.sqlite_source' => [
            'driver' => 'sqlite',
            'database' => $sqlitePath,
            'prefix' => '',
        ]]);


        Schema::connection($this->targetDbConfig['driver'])->disableForeignKeyConstraints();
        
        $tables = $this->getSqliteTables();
        $progressBar = $this->output->createProgressBar(count($tables));
        $progressBar->start();

        foreach ($tables as $table) {
            $this->migrateTable($table);
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);
        Schema::connection($this->targetDbConfig['driver'])->enableForeignKeyConstraints();
    }

    /**
     * 获取 SQLite 数据库中的所有用户表
     */
    private function getSqliteTables(): array
    {
        $tables = DB::connection('sqlite_source')->select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' AND name != 'migrations'");
        return array_column($tables, 'name');
    }

    /**
     * 通用表迁移函数
     */
    private function migrateTable(string $tableName)
    {
        try {
            // 获取目标表（MySQL/PostgreSQL）的详细列信息
            $targetColumns = $this->getTargetTableColumnsDetails($tableName);

            // 分批从 SQLite 读取数据并插入到目标数据库
            DB::connection('sqlite_source')->table($tableName)->orderBy('id')->chunk(200, function ($rows) use ($targetColumns, $tableName) {
                $insertData = [];
                foreach ($rows as $row) {
                    $rowData = (array)$row;
                    $processedData = [];
                    foreach ($targetColumns as $column) {
                        $columnName = $column->getName();
                        
                        if (array_key_exists($columnName, $rowData)) {
                            $value = $rowData[$columnName];
                            // 处理 NULL 值
                            if ($value === null && $column->getNotnull() && !$column->getAutoincrement()) {
                                $defaultValue = $column->getDefault();
                                if ($defaultValue !== null) {
                                     // Let the database handle the default value on insert
                                    continue;
                                }
                                // 提供一个安全的默认值
                                $value = $this->getSafeDefaultValue($column);
                            }
                            $processedData[$columnName] = $value;
                        }
                    }
                    $insertData[] = $processedData;
                }

                if (!empty($insertData)) {
                    DB::connection($this->targetDbConfig['driver'])->table($tableName)->insert($insertData);
                }
            });
        } catch(\Exception $e) {
            $this->error("\nCould not migrate table '{$tableName}'. Error: {$e->getMessage()}");
            $this->warn("Skipping table '{$tableName}'.");
        }
    }

    /**
     * 获取目标数据库表的详细列信息
     */
    private function getTargetTableColumnsDetails(string $tableName): array
    {
        $schemaManager = DB::connection($this->targetDbConfig['driver'])->getDoctrineSchemaManager();
        if (!$schemaManager->tablesExist([$tableName])) {
             throw new \Exception("Table '{$tableName}' does not exist in the target database. Please ensure all migrations have run correctly.");
        }
        $tableDetails = $schemaManager->listTableDetails($tableName);
        return $tableDetails->getColumns();
    }

    /**
     * 根据列的类型和目标数据库，为 NULL 值提供一个安全的默认值
     */
    private function getSafeDefaultValue(Column $column)
    {
        $type = strtolower($column->getType()->getName());
        $defaultValues = $this->targetDbConfig['default_values'];

        // 处理类型别名，例如 'int' vs 'integer'
        $typeMap = [
            'int' => 'integer', 'int4' => 'integer', // PostgreSQL
            'int8' => 'bigint', // PostgreSQL
            'varchar' => 'string',
            'bool' => 'boolean', // PostgreSQL
            'timestamp without time zone' => 'timestamp', // PostgreSQL
        ];
        $normalizedType = $typeMap[$type] ?? $type;

        return $defaultValues[$normalizedType] ?? '';
    }
}
