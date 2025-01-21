<?php

namespace App\Services\Plugin;

use Illuminate\Support\Facades\Event;

class HookManager
{
    /**
     * 触发钩子
     *
     * @param string $hook 钩子名称
     * @param mixed $payload 传递给钩子的数据
     * @return mixed
     */
    public static function call(string $hook, mixed $payload = null): mixed
    {
        return Event::dispatch($hook, [$payload]);
    }

    /**
     * 注册钩子监听器
     *
     * @param string $hook 钩子名称
     * @param callable $callback 回调函数
     * @return void
     */
    public static function register(string $hook, callable $callback): void
    {
        Event::listen($hook, $callback);
    }

    /**
     * 移除钩子监听器
     *
     * @param string $hook 钩子名称
     * @return void
     */
    public static function remove(string $hook): void
    {
        Event::forget($hook);
    }
}