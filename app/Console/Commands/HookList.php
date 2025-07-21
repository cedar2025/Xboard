<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class HookList extends Command
{
  protected $signature = 'hook:list';
  protected $description = '列出系统支持的所有 hooks（静态扫描代码）';

  public function handle()
  {
    $paths = [base_path('app'), base_path('plugins')];
    $hooks = collect();
    $pattern = '/HookManager::(call|filter|register|registerFilter)\([\'\"]([a-zA-Z0-9_.-]+)[\'\"]/';

    foreach ($paths as $path) {
      $files = collect(
        is_dir($path) ? (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path))) : []
      )->filter(fn($f) => Str::endsWith($f, '.php'));
      foreach ($files as $file) {
        $content = @file_get_contents($file);
        if ($content && preg_match_all($pattern, $content, $matches)) {
          foreach ($matches[2] as $hook) {
            $hooks->push($hook);
          }
        }
      }
    }
    $hooks = $hooks->unique()->sort()->values();
    if ($hooks->isEmpty()) {
      $this->info('未扫描到任何 hook');
    } else {
      $this->info('All Supported Hooks:');
      foreach ($hooks as $hook) {
        $this->line('  ' . $hook);
      }
    }
  }
}