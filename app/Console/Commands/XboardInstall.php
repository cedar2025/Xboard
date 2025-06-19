<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use App\Models\User;
use App\Utils\Helper;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\text;
use function Laravel\Prompts\note;

class XboardInstall extends Command
{
    protected $signature = 'xboard:install';
    protected $description = 'xboard 初始化安装';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        try {
            $isDocker = file_exists('/.dockerenv');
            $enableSqlite = getenv('ENABLE_SQLITE', false);
            $enableRedis = getenv('ENABLE_REDIS', false);
            $adminAccount = getenv('ADMIN_ACCOUNT', false);

            $this->info("__    __ ____                      _  ");
            $this->info("\ \  / /| __ )  ___   __ _ _ __ __| | ");
            $this->info(" \ \/ / | __ \ / _ \ / _` | '__/ _` | ");
            $this->info(" / /\ \ | |_) | (_) | (_| | | | (_| | ");
            $this->info("/_/  \_\|____/ \___/ \__,_|_|  \__,_| ");

            if (
                (File::exists(base_path() . '/.env') && $this->getEnvValue('INSTALLED'))
                || (getenv('INSTALLED', false) && $isDocker)
            ) {
                $securePath = admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))));
                $this->info("访问 http(s)://你的站点/{$securePath} 进入管理面板，你可以在用户中心修改你的密码。");
                $this->warn("如需重新安装请清空目录下 .env 文件的内容（Docker安装方式不可以删除此文件）");
                note('rm .env && touch .env');
                return;
            }

            if (is_dir(base_path() . '/.env')) {
                $this->error('😔：安装失败，Docker环境下安装请保留空的 .env 文件');
                return;
            }

            // ---------- 数据库类型选择 ----------
            $validDbTypes = ['mysql', 'sqlite', 'pgsql'];
            $databaseType = $enableSqlite ? 'sqlite' : strtolower(
                text(
                    label: '请选择数据库类型 (mysql/sqlite/pgsql)',
                    default: 'mysql',
                    validate: fn($v) => in_array(strtolower($v), $validDbTypes) ? null : '只能输入 mysql、sqlite 或 pgsql'
                )
            );

            $envConfig = [];

            if ($databaseType === 'sqlite') {
                $sqliteFile = '.docker/.data/database.sqlite';
                if (!file_exists(base_path($sqliteFile))) {
                    if (!touch(base_path($sqliteFile))) {
                        $this->error("无法创建 SQLite 数据库文件: $sqliteFile");
                        return;
                    }
                }

                $envConfig = [
                    'DB_CONNECTION' => 'sqlite',
                    'DB_DATABASE' => $sqliteFile,
                    'DB_HOST' => '',
                    'DB_USERNAME' => '',
                    'DB_PASSWORD' => '',
                ];

                try {
                    Config::set("database.default", 'sqlite');
                    Config::set("database.connections.sqlite.database", base_path($envConfig['DB_DATABASE']));
                    DB::purge('sqlite');
                    DB::connection('sqlite')->getPdo();

                    $tables = DB::connection('sqlite')->getPdo()->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(\PDO::FETCH_COLUMN);
                    if (!blank($tables)) {
                        if (confirm(label: '检测到已有数据，是否清空数据库？', default: false)) {
                            $this->call('db:wipe', ['--force' => true]);
                        } else {
                            return;
                        }
                    }
                } catch (\Exception $e) {
                    $this->error("数据库连接失败：" . $e->getMessage());
                    return;
                }

            } else {
                $isValid = false;
                while (!$isValid) {
                    $envConfig = [
                        'DB_CONNECTION' => $databaseType,
                        'DB_HOST' => text('请输入数据库地址', default: '127.0.0.1'),
                        'DB_PORT' => text('请输入数据库端口', default: $databaseType === 'mysql' ? '3306' : '5432'),
                        'DB_DATABASE' => text('请输入数据库名', default: 'xboard'),
                        'DB_USERNAME' => text('请输入数据库用户名', default: $databaseType === 'mysql' ? 'root' : 'postgres'),
                        'DB_PASSWORD' => text('请输入数据库密码', default: ''),
                    ];

                    try {
                        Config::set("database.default", $databaseType);
                        Config::set("database.connections.{$databaseType}.host", $envConfig['DB_HOST']);
                        Config::set("database.connections.{$databaseType}.port", $envConfig['DB_PORT']);
                        Config::set("database.connections.{$databaseType}.database", $envConfig['DB_DATABASE']);
                        Config::set("database.connections.{$databaseType}.username", $envConfig['DB_USERNAME']);
                        Config::set("database.connections.{$databaseType}.password", $envConfig['DB_PASSWORD']);

                        DB::purge($databaseType);
                        DB::connection($databaseType)->getPdo();

                        $tables = $databaseType === 'mysql'
                            ? DB::select('SHOW TABLES')
                            : DB::select("SELECT tablename FROM pg_tables WHERE schemaname='public'");

                        if (!blank($tables)) {
                            if (confirm(label: '检测到已有数据，是否清空数据库？', default: false)) {
                                $this->call('db:wipe', ['--force' => true]);
                            } else {
                                $isValid = false;
                                continue;
                            }
                        }

                        $isValid = true;
                    } catch (\Exception $e) {
                        $this->error("数据库连接失败：" . $e->getMessage());
                    }
                }
            }

            // ---------- Redis 配置 ----------
            $isRedisValid = false;
            while (!$isRedisValid) {
                if ($isDocker && ($enableRedis || confirm(label: '是否启用 Docker Redis？', default: true))) {
                    $envConfig['REDIS_HOST'] = '/data/redis.sock';
                    $envConfig['REDIS_PORT'] = 0;
                    $envConfig['REDIS_PASSWORD'] = null;
                } else {
                    $envConfig['REDIS_HOST'] = text('请输入 Redis 地址', default: '127.0.0.1');
                    $envConfig['REDIS_PORT'] = text('请输入 Redis 端口', default: '6379');
                    $envConfig['REDIS_PASSWORD'] = text('请输入 Redis 密码 (可留空)', default: '');
                }

                // 提前注入 config
                Config::set('database.redis.client', 'phpredis');
                Config::set('database.redis.default', [
                    'host' => $envConfig['REDIS_HOST'],
                    'port' => (int) $envConfig['REDIS_PORT'],
                    'password' => $envConfig['REDIS_PASSWORD'] ?: null,
                    'database' => 0,
                ]);
                Config::set('cache.default', 'redis');

                try {
                    $redis = new \Illuminate\Redis\RedisManager(app(), 'phpredis', [
                        'default' => Config::get('database.redis.default'),
                    ]);
                    $redis->ping();
                    $isRedisValid = true;
                } catch (\Exception $e) {
                    $this->error("Redis 连接失败：" . $e->getMessage());
                }
            }

            $envConfig['APP_KEY'] = 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC'));

            // 写入 .env
            if (!copy(base_path('.env.example'), base_path('.env'))) {
                abort(500, '复制 .env 文件失败，请检查权限');
            }

            $email = $adminAccount ?: text(
                '请输入管理员邮箱',
                default: 'admin@demo.com',
                validate: fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : '邮箱格式不正确'
            );

            $password = Helper::guid(false);
            $this->saveToEnv($envConfig);

            $this->call('config:cache');
            try {
                Artisan::call('cache:clear');
            } catch (\Exception $e) {
                $this->warn("Redis 缓存清理失败：" . $e->getMessage());
            }

            Artisan::call('migrate', ['--force' => true]);
            $this->info('数据库初始化完成');
            $this->info(Artisan::output());

            $this->info('开始创建管理员...');
            if (!self::registerAdmin($email, $password)) {
                abort(500, '管理员创建失败');
            }

            $securePath = hash('crc32b', config('app.key'));
            $envConfig['INSTALLED'] = true;
            $this->saveToEnv($envConfig);

            $this->info("🎉 安装完成，管理员账号：{$email}");
            $this->info("管理员密码：{$password}");
            $this->info("访问 http(s)://你的站点/{$securePath} 登录后台");

        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    public static function registerAdmin($email, $password)
    {
        $user = new User();
        $user->email = $email;
        if (strlen($password) < 8) abort(500, '管理员密码必须至少8位');
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
        $user->is_admin = 1;
        return $user->save();
    }

    private function set_env_var($key, $value)
    {
        $value = !strpos($value, ' ') ? $value : '"' . $value . '"';
        $key = strtoupper($key);
        $envPath = app()->environmentFilePath();
        $contents = file_get_contents($envPath);
        if (preg_match("/^{$key}=.*/m", $contents)) {
            $contents = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $contents);
        } else {
            $contents .= "\n{$key}={$value}\n";
        }
        return file_put_contents($envPath, $contents) !== false;
    }

    private function saveToEnv($data = [])
    {
        foreach ($data as $key => $value) {
            $this->set_env_var($key, $value);
        }
    }

    function getEnvValue($key, $default = null)
    {
        $dotenv = \Dotenv\Dotenv::createImmutable(base_path());
        $dotenv->load();
        return Env::get($key, $default);
    }
}
