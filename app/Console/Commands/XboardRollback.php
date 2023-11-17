<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class XboardRollback extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xboard:rollback';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'xboard 回滚';

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
        $this->info('正在回滚数据库请稍等...');
            \Artisan::call("migrate:rollback");
            $this->info(\Artisan::output());
    }
}
