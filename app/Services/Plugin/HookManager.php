<?php

namespace App\Services\Plugin;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Support\Facades\App;

class HookManager
{
    /**
     * Container for storing action hooks
     * 
     * Uses request() to store hook data within the cycle to avoid Octane memory leaks
     */
    public static function getActions(): array
    {
        if (!App::has('hook.actions')) {
            App::instance('hook.actions', []);
        }

        return App::make('hook.actions');
    }

    /**
     * Container for storing filter hooks
     */
    public static function getFilters(): array
    {
        if (!App::has('hook.filters')) {
            App::instance('hook.filters', []);
        }

        return App::make('hook.filters');
    }

    /**
     * Set action hooks
     */
    protected static function setActions(array $actions): void
    {
        App::instance('hook.actions', $actions);
    }

    /**
     * Set filter hooks
     */
    protected static function setFilters(array $filters): void
    {
        App::instance('hook.filters', $filters);
    }

    /**
     * Generate unique identifier for callback
     * 
     * @param callable $callback
     * @return string
     */
    protected static function getCallableId(callable $callback): string
    {
        if (is_object($callback)) {
            return spl_object_hash($callback);
        }

        if (is_array($callback) && count($callback) === 2) {
            [$class, $method] = $callback;

            if (is_object($class)) {
                return spl_object_hash($class) . '::' . $method;
            } else {
                return (string) $class . '::' . $method;
            }
        }

        if (is_string($callback)) {
            return $callback;
        }

        return 'callable_' . uniqid();
    }

    /**
     * Intercept response
     *
     * @param SymfonyResponse|string|array $response New response content
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
     * Trigger action hook
     *
     * @param string $hook Hook name
     * @param mixed $payload Data passed to hook
     * @return void
     */
    public static function call(string $hook, mixed $payload = null): void
    {
        $actions = self::getActions();

        if (!isset($actions[$hook])) {
            return;
        }

        ksort($actions[$hook]);

        foreach ($actions[$hook] as $callbacks) {
            foreach ($callbacks as $callback) {
                $callback($payload);
            }
        }
    }

    /**
     * Trigger filter hook
     *
     * @param string $hook Hook name
     * @param mixed $value Value to filter
     * @param mixed ...$args Other parameters
     * @return mixed
     */
    public static function filter(string $hook, mixed $value, mixed ...$args): mixed
    {
        $filters = self::getFilters();

        if (!isset($filters[$hook])) {
            return $value;
        }

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
     * Register action hook listener
     *
     * @param string $hook Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority
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

        $actions[$hook][$priority][self::getCallableId($callback)] = $callback;

        self::setActions($actions);
    }

    /**
     * Register filter hook
     *
     * @param string $hook Hook name
     * @param callable $callback Callback function
     * @param int $priority Priority
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

        $filters[$hook][$priority][self::getCallableId($callback)] = $callback;

        self::setFilters($filters);
    }

    /**
     * Remove hook listener
     *
     * @param string $hook Hook name
     * @param callable|null $callback Callback function
     * @return void
     */
    public static function remove(string $hook, ?callable $callback = null): void
    {
        $actions = self::getActions();
        $filters = self::getFilters();

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

        $callbackId = self::getCallableId($callback);

        if (isset($actions[$hook])) {
            foreach ($actions[$hook] as $priority => $callbacks) {
                if (isset($callbacks[$callbackId])) {
                    unset($actions[$hook][$priority][$callbackId]);

                    if (empty($actions[$hook][$priority])) {
                        unset($actions[$hook][$priority]);
                    }

                    if (empty($actions[$hook])) {
                        unset($actions[$hook]);
                    }
                }
            }
            self::setActions($actions);
        }

        if (isset($filters[$hook])) {
            foreach ($filters[$hook] as $priority => $callbacks) {
                if (isset($callbacks[$callbackId])) {
                    unset($filters[$hook][$priority][$callbackId]);

                    if (empty($filters[$hook][$priority])) {
                        unset($filters[$hook][$priority]);
                    }

                    if (empty($filters[$hook])) {
                        unset($filters[$hook]);
                    }
                }
            }
            self::setFilters($filters);
        }
    }

    /**
     * Check if hook exists
     *
     * @param string $hook Hook name
     * @return bool
     */
    public static function hasHook(string $hook): bool
    {
        $actions = self::getActions();
        $filters = self::getFilters();

        return isset($actions[$hook]) || isset($filters[$hook]);
    }

    /**
     * Clear all hooks (called when Octane resets)
     */
    public static function reset(): void
    {
        App::instance('hook.actions', []);
        App::instance('hook.filters', []);
    }
}