<?php

namespace App\Services\Plugin;

use TorMorten\Eventy\Facades\Events as Eventy;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class HookManager
{
    /**
     * 拦截响应
     *
     * @param SymfonyResponse|string|array $response 新的响应内容
     * @return never
     * @throws \Exception
     */
    public static function intercept(SymfonyResponse|string|array $response): never
    {
        if (is_string($response)) {
            $response = response($response);
        } elseif (is_array($response)) {
            $response = response()->json($response);
        }
        
        throw new InterceptResponseException($response);
    }

    /**
     * 触发动作钩子
     *
     * @param string $hook 钩子名称
     * @param mixed $payload 传递给钩子的数据
     * @return void
     */
    public static function call(string $hook, mixed $payload = null): void
    {
        Eventy::action($hook, $payload);
    }

    /**
     * 触发过滤器钩子
     *
     * @param string $hook 钩子名称
     * @param mixed $value 要过滤的值
     * @param mixed ...$args 其他参数
     * @return mixed
     */
    public static function filter(string $hook, mixed $value, mixed ...$args): mixed
    {
        return Eventy::filter($hook, $value, ...$args);
    }

    /**
     * 注册动作钩子监听器
     *
     * @param string $hook 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级
     * @return void
     */
    public static function register(string $hook, callable $callback, int $priority = 20): void
    {
        Eventy::addAction($hook, $callback, $priority);
    }

    /**
     * 注册过滤器钩子
     *
     * @param string $hook 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级
     * @return void
     */
    public static function registerFilter(string $hook, callable $callback, int $priority = 20): void
    {
        Eventy::addFilter($hook, $callback, $priority);
    }

    /**
     * 移除钩子监听器
     *
     * @param string $hook 钩子名称
     * @param callable|null $callback 回调函数
     * @return void
     */
    public static function remove(string $hook, ?callable $callback = null): void
    {
        Eventy::removeAction($hook, $callback);
        Eventy::removeFilter($hook, $callback);
    }
}