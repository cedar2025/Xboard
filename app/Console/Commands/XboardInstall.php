<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use App\Models\User;
use App\Utils\Helper;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\DB;

class XboardInstall extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xboard:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'xboard 初始化安装';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
            \Artisan::call('config:clear');
            $isDocker = env('docker', false);

            $this->info("__    __ ____                      _  ");
            $this->info("\ \  / /| __ )  ___   __ _ _ __ __| | ");
            $this->info(" \ \/ / | __ \ / _ \ / _` | '__/ _` | ");
            $this->info(" / /\ \ | |_) | (_) | (_| | | | (_| | ");
            $this->info("/_/  \_\|____/ \___/ \__,_|_|  \__,_| ");
            if (\File::exists(base_path() . '/.env') && $this->getEnvValue('INSTALLED')) {
                $securePath = admin_setting('secure_path', admin_setting('frontend_admin_path', hash('crc32b', config('app.key'))));
                $this->info("访问 http(s)://你的站点/{$securePath} 进入管理面板，你可以在用户中心修改你的密码。");
                $this->warn("如需重新安装请清空目录下 .env 文件的内容（Docker安装方式不可以删除此文件）");
                $this->warn("快捷清空.env命令： \"rm .env && touch .env\"");
                \Artisan::call('config:cache');
                return ;
            }
            $envConfig['APP_KEY'] = 'base64:' . base64_encode(Encrypter::generateKey('AES-256-CBC'));
            // 选择是否使用Sqlite
            if( $this->ask('是否启用Sqlite(无需额外安装)代替Mysql(默认不启用 y/n)','n') == 'y' ) {
                $sqliteFile = '.docker/.data/database.sqlite';
                if (!file_exists(base_path($sqliteFile))) {
                    // 创建空文件
                    if (touch(base_path($sqliteFile))) {
                        echo "sqlite创建成功: $sqliteFile";
                    } else {
                        echo "sqlite创建失败";
                    }
                }
                $envConfig = [
                    'DB_CONNECTION' => 'sqlite',
                    'DB_DATABASE' => $sqliteFile,
                    'DB_HOST' => '',
                    'DB_USERNAME' => '',
                    'DB_PASSWORD' => '',
                ];
            }else{
                $envConfig = [
                    'DB_CONNECTION' => 'mysql',
                    'DB_HOST' => $this->ask("请输入数据库地址(默认:" . ($isDocker ? '172.17.0.1' :'127.0.0.1') .")", ($isDocker ? '172.17.0.1' :'127.0.0.1')),
                    'DB_PORT' => $this->ask('请输入数据库端口(默认:3306)', '3306'),
                    'DB_DATABASE' => $this->ask('请输入数据库名', 'xboard'),
                    'DB_USERNAME' => $this->ask('请输入数据库用户名'),
                    'DB_PASSWORD' => $this->ask('请输入数据库密码'),
                ];   
            }
            $envConfig['INSTALLED'] = 'true';
            // 判断是否为Docker环境
            if ($isDocker == 'true' && ($this->ask('是否启用Docker内置的Redis(默认启用 y/n)','y') === 'y')){
                $envConfig['REDIS_HOST']  = '/run/redis-socket/redis.sock';
                $envConfig['REDIS_PORT']  = 0;
                $envConfig['REDIS_PASSWORD'] = null;
            }else{
                $envConfig['REDIS_HOST']  =  $this->ask('请输入redis地址(默认: 127.0.0.1)', '127.0.0.1');
                $envConfig['REDIS_PORT']  = $this->ask('请输入redis端口(默认: 6379)', '6379');
                $envConfig['REDIS_PASSWORD'] = $this->ask('请输入redis密码(默认: null)', null);
            }

            if (!copy(base_path() . '/.env.example', base_path() . '/.env')) {
                abort(500, '复制环境文件失败，请检查目录权限');
            }
            $this->saveToEnv($envConfig);

            \Artisan::call('config:clear');
            \Artisan::call('config:cache');
            \Artisan::call('cache:clear');
            
            $this->info('正在清空数据库请稍等');
            \Artisan::call('db:wipe');
            $this->info('数据库清空完成');
            $this->info('正在导入数据库请稍等...');
            \Artisan::call("migrate");
            $this->info(\Artisan::output());
            
            $this->info('数据库导入完成');
            $email = '';
            while (!$email) {
                $email = $this->ask('请输入管理员邮箱?');
            }
            $password = Helper::guid(false);
            if (!$this->registerAdmin($email, $password)) {
                abort(500, '管理员账号注册失败，请重试');
            }

            $this->info('一切就绪');
            $this->info("管理员邮箱：{$email}");
            $this->info("管理员密码：{$password}");

            $defaultSecurePath = hash('crc32b', config('app.key'));
            $this->info("访问 http(s)://你的站点/{$defaultSecurePath} 进入管理面板，你可以在用户中心修改你的密码。");
        } catch (\Exception $e) {
            $this->error($e);
        }
    }

    public function registerAdmin($email, $password)
    {
        $user = new User();
        $user->email = $email;
        if (strlen($password) < 8) {
            abort(500, '管理员密码长度最小为8位字符');
        }
        $user->password = password_hash($password, PASSWORD_DEFAULT);
        $user->uuid = Helper::guid(true);
        $user->token = Helper::guid();
        $user->is_admin = 1;
        return $user->save();
    }

    private function saveToEnv($data = [])
    {
        function set_env_var($key, $value)
        {
            if (! is_bool(strpos($value, ' '))) {
                $value = '"' . $value . '"';
            }
            $key = strtoupper($key);

            $envPath = app()->environmentFilePath();
            $contents = file_get_contents($envPath);

            preg_match("/^{$key}=[^\r\n]*/m", $contents, $matches);

            $oldValue = count($matches) ? $matches[0] : '';

            if ($oldValue) {
                $contents = str_replace("{$oldValue}", "{$key}={$value}", $contents);
            } else {
                $contents = $contents . "\n{$key}={$value}\n";
            }

            $file = fopen($envPath, 'w');
            fwrite($file, $contents);
            return fclose($file);
        }
        foreach($data as $key => $value) {
            set_env_var($key, $value);
        }
        return true;
    }

    function getEnvValue($key, $default = null)
    {
        $dotenv = \Dotenv\Dotenv::createImmutable(base_path());
        $dotenv->load();

        return Env::get($key, $default);
    }
}
