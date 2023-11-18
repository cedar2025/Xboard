<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Cloud\Storage\StorageClient;

class BackupDatabase extends Command
{
    protected $signature = 'backup:upload-cloud';
    protected $description = '备份数据库并上传到 Google Cloud Storage';

    public function handle()
    {
        // 判断是否存在必要配置
        $requiredConfigs = ['database.connections.mysql', 'cloud_storage.google_cloud.key_file', 'cloud_storage.google_cloud.storage_bucket'];
        foreach ($requiredConfigs as $config) {
            if (config($config) === null) {
                $this->error("❌：缺少必要配置项: $config ， 取消备份");
                return;
            }
        }

        // 数据库备份逻辑（用你自己的逻辑替换）
        $databaseBackupPath = storage_path('backup/' .  now()->format('Y-m-d_H-i-s') . '_database_backup.sql');
        try{
            if (config('database.default') === 'mysql'){
                $this->info("1️⃣：开始备份Mysql");
                \Spatie\DbDumper\Databases\MySql::create()
                    ->setDbName(config('database.connections.mysql.database'))
                    ->setUserName(config('database.connections.mysql.username'))
                    ->setPassword(config('database.connections.mysql.password'))
                    ->dumpToFile($databaseBackupPath);
                $this->info("2️⃣：Mysql备份完成");
            }elseif(config('database.default') === 'sqlite'){
                $this->info("1️⃣：开始备份Sqlite");
                \Spatie\DbDumper\Databases\Sqlite::create()
                    ->setDbName(config('database.connections.sqlite.database'))
                    ->dumpToFile($databaseBackupPath);
                $this->info("2️⃣：Sqlite备份完成");
            }
            $this->info("3️⃣：开始将备份上传到Google Cloud");
            // Google Cloud Storage 配置
            $storage = new StorageClient([
                'keyFilePath' => config('cloud_storage.google_cloud.key_file'),
            ]);
            $bucket = $storage->bucket(config('cloud_storage.google_cloud.storage_bucket'));
            $objectName = 'backup/' . now()->format('Y-m-d_H-i-s') . '_database_backup.sql';
            // 上传文件
            $bucket->upload(fopen($databaseBackupPath, 'r'), [
                'name' => $objectName,
            ]);
    
            // 输出文件链接
            
            \Log::channel('backup')->info("🎉：数据库备份已上传到 Google Cloud Storage: $objectName");
            $this->info("🎉：数据库备份已上传到 Google Cloud Storage: $objectName");
        }catch(\Exception $e){
            \Log::channel('backup')->error("😔：数据库备份失败" . $e->getMessage());
            $this->error("😔：数据库备份失败" . $e->getMessage());
        }
        // 开始删除本地备份
        \File::delete($databaseBackupPath);
    }
}
