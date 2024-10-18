<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Cloud\Storage\StorageClient;
use Symfony\Component\Process\Process;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {upload?}';
    protected $description = '备份数据库并上传到 Google Cloud Storage';

    public function handle()
    {
        $isUpload = $this->argument('upload');
        // 如果是上传到云端则判断是否存在必要配置
        if($isUpload){
            $requiredConfigs = ['database.connections.mysql', 'cloud_storage.google_cloud.key_file', 'cloud_storage.google_cloud.storage_bucket'];
            foreach ($requiredConfigs as $config) {
                if (blank(config($config))) {
                    $this->error("❌：缺少必要配置项: $config ， 取消备份");
                    return;
                }
            }
        }

        // 数据库备份逻辑
        $databaseBackupPath = storage_path('backup/' .  now()->format('Y-m-d_H-i-s') . '_' . config('database.connections.mysql.database') . '_database_backup.sql');
        $compressedBackupPath = $databaseBackupPath . '.gz';
        try{
            if (config('database.default') === 'mysql'){
                $this->info("1️⃣：开始备份Mysql");
                \Spatie\DbDumper\Databases\MySql::create()
                    ->setHost(config('database.connections.mysql.host'))
                    ->setPort(config('database.connections.mysql.port'))
                    ->setDbName(config('database.connections.mysql.database'))
                    ->setUserName(config('database.connections.mysql.username'))
                    ->setPassword(config('database.connections.mysql.password'))
                    ->dumpToFile($databaseBackupPath);
                $this->info("2️⃣：Mysql备份完成");
            }elseif(config('database.default') === 'sqlite'){
                $databaseBackupPath = storage_path('backup/' .  now()->format('Y-m-d_H-i-s') . '_sqlite'  . '_database_backup.sql');
                $this->info("1️⃣：开始备份Sqlite");
                \Spatie\DbDumper\Databases\Sqlite::create()
                    ->setDbName(config('database.connections.sqlite.database'))
                    ->dumpToFile($databaseBackupPath);
                $this->info("2️⃣：Sqlite备份完成");
            }else{
                $this->error('备份失败，你的数据库不是sqlite或者mysql');
                return;
            }
            $this->info('3️⃣：开始压缩备份文件');
            // 使用 gzip 压缩备份文件
            $compressedBackupPath = $databaseBackupPath . '.gz';
            $gzipCommand = new Process(["gzip", "-c", $databaseBackupPath]);
            $gzipCommand->run();

            // 检查压缩是否成功
            if ($gzipCommand->isSuccessful()) {
                // 压缩成功，你可以删除原始备份文件
                file_put_contents($compressedBackupPath, $gzipCommand->getOutput());
                $this->info('4️⃣：文件压缩成功');
                unlink($databaseBackupPath);
            } else {
                // 压缩失败，处理错误
                echo $gzipCommand->getErrorOutput();
                $this->error('😔：文件压缩失败');
                unlink($databaseBackupPath);
                return;
            }
            if (!$isUpload){
                $this->info("🎉：数据库成功备份到：$compressedBackupPath");
            }else{
                // 传到云盘
                $this->info("5️⃣：开始将备份上传到Google Cloud");
                // Google Cloud Storage 配置
                $storage = new StorageClient([
                    'keyFilePath' => config('cloud_storage.google_cloud.key_file'),
                ]);
                $bucket = $storage->bucket(config('cloud_storage.google_cloud.storage_bucket'));
                $objectName = 'backup/' . now()->format('Y-m-d_H-i-s') . '_database_backup.sql.gz';
                // 上传文件
                $bucket->upload(fopen($compressedBackupPath, 'r'), [
                    'name' => $objectName,
                ]);

                // 输出文件链接
                \Log::channel('backup')->info("🎉：数据库备份已上传到 Google Cloud Storage: $objectName");
                $this->info("🎉：数据库备份已上传到 Google Cloud Storage: $objectName");
                \File::delete($compressedBackupPath);
            }
        }catch(\Exception $e){
            \Log::channel('backup')->error("😔：数据库备份失败 \n" . $e);
            $this->error("😔：数据库备份失败\n" . $e);
            \File::delete($compressedBackupPath);
        }
    }
}
