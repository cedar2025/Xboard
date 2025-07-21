<?php

namespace App\Console\Commands;

use App\Services\ThemeService;
use App\Services\UpdateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class XboardUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xboard:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'xboard 更新';

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
        $this->info('正在导入数据库请稍等...');
        Artisan::call("migrate");
        $this->info(Artisan::output());
        Artisan::call('horizon:terminate');
        Artisan::call('reset:traffic', ['--fix-null' => true]);
        $updateService = new UpdateService();
        $updateService->updateVersionCache();
        $themeService = app(ThemeService::class);
        $themeService->refreshCurrentTheme();
        $this->info('更新完毕，队列服务已重启，你无需进行任何操作。');
    }
}
