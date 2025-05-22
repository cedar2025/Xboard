<?php

namespace App\Services\Plugin;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Support\Facades\App;

class HookManager
{
    /**
     * 存储动作钩子的容器
     * 
     * 使用request()存储周期内的钩子数据，避免Octane内存泄漏
     */
    protected static function getActions(): array
    {
        if (!App::has('hook.actions')) {
            App::instance('hook.actions', []);
        }

        return App::make('hook.actions');
    }

    /**
     * 存储过滤器钩子的容器
     */
    protected static function getFilters(): array
    {
        if (!App::has('hook.filters')) {
            App::instance('hook.filters', []);
        }

        return App::make('hook.filters');
    }

    /**
     * 设置动作钩子
     */
    protected static function setActions(array $actions): void
    {
        App::instance('hook.actions', $actions);
    }

    /**
     * 设置过滤器钩子
     */
    protected static function setFilters(array $filters): void
    {
        App::instance('hook.filters', $filters);
    }

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
        $actions = self::getActions();

        if (!isset($actions[$hook])) {
            return;
        }

        // 按优先级排序
        ksort($actions[$hook]);

        foreach ($actions[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                $callback($payload);
            }
        }
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
        $filters = self::getFilters();

        if (!isset($filters[$hook])) {
            return $value;
        }

        // 按优先级排序
        ksort($filters[$hook]);

        $result = $value;
        foreach ($filters[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                $result = $callback($result, ...$args);
            }
        }

        return $result;
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
        $actions = self::getActions();

        if (!isset($actions[$hook])) {
            $actions[$hook] = [];
        }

        if (!isset($actions[$hook][$priority])) {
            $actions[$hook][$priority] = [];
        }

        // 使用随机键存储回调，避免相同优先级覆盖
        $actions[$hook][$priority][spl_object_hash($callback)] = $callback;

        self::setActions($actions);
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
        $filters = self::getFilters();

        if (!isset($filters[$hook])) {
            $filters[$hook] = [];
        }

        if (!isset($filters[$hook][$priority])) {
            $filters[$hook][$priority] = [];
        }

        // 使用随机键存储回调，避免相同优先级覆盖
        $filters[$hook][$priority][spl_object_hash($callback)] = $callback;

        self::setFilters($filters);
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
        $actions = self::getActions();
        $filters = self::getFilters();

        // 如果回调为null，直接移除整个钩子
        if ($callback === null) {
            if (isset($actions[$hook])) {
                unset($actions[$hook]);
                self::setActions($actions);
            }

            if (isset($filters[$hook])) {
                unset($filters[$hook]);
                self::setFilters($filters);
            }

            return;
        }

        // 移除特定回调
        $callbackId = spl_object_hash($callback);

        // 从actions中移除
        if (isset($actions[$hook])) {
            foreach ($actions[$hook] as $priority => $callbacks) {
                if (isset($callbacks[$callbackId])) {
                    unset($actions[$hook][$priority][$callbackId]);

                    // 如果优先级下没有回调了，删除该优先级
                    if (empty($actions[$hook][$priority])) {
                        unset($actions[$hook][$priority]);
                    }

                    // 如果钩子下没有任何优先级了，删除该钩子
                    if (empty($actions[$hook])) {
                        unset($actions[$hook]);
                    }
                }
            }
            self::setActions($actions);
        }

        // 从filters中移除
        if (isset($filters[$hook])) {
            foreach ($filters[$hook] as $priority => $callbacks) {
                if (isset($callbacks[$callbackId])) {
                    unset($filters[$hook][$priority][$callbackId]);

                    // 如果优先级下没有回调了，删除该优先级
                    if (empty($filters[$hook][$priority])) {
                        unset($filters[$hook][$priority]);
                    }

                    // 如果钩子下没有任何优先级了，删除该钩子
                    if (empty($filters[$hook])) {
                        unset($filters[$hook]);
                    }
                }
            }
            self::setFilters($filters);
        }
    }

    /**
     * 检查是否存在钩子
     *
     * @param string $hook 钩子名称
     * @return bool
     */
    public static function hasHook(string $hook): bool
    {
        $actions = self::getActions();
        $filters = self::getFilters();

        return isset($actions[$hook]) || isset($filters[$hook]);
    }

    /**
     * 清理所有钩子（在Octane重置时调用）
     */
    public static function reset(): void
    {
        App::instance('hook.actions', []);
        App::instance('hook.filters', []);
    }
}