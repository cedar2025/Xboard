<?php

namespace App\Support;

use Illuminate\Contracts\Container\Container;

class ProtocolManager
{
    /**
     * @var Container Laravel容器实例
     */
    protected $container;

    /**
     * @var array 缓存的协议类列表
     */
    protected $protocolClasses = [];

    /**
     * 构造函数
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * 发现并注册所有协议类
     *
     * @return self
     */
    public function registerAllProtocols()
    {
        if (empty($this->protocolClasses)) {
            $files = glob(app_path('Protocols') . '/*.php');

            foreach ($files as $file) {
                $className = 'App\\Protocols\\' . basename($file, '.php');

                if (class_exists($className) && is_subclass_of($className, AbstractProtocol::class)) {
                    $this->protocolClasses[] = $className;
                }
            }
        }

        return $this;
    }

    /**
     * 获取所有注册的协议类
     *
     * @return array
     */
    public function getProtocolClasses()
    {
        if (empty($this->protocolClasses)) {
            $this->registerAllProtocols();
        }

        return $this->protocolClasses;
    }

    /**
     * 获取所有协议的标识
     *
     * @return array
     */
    public function getAllFlags()
    {
        return collect($this->getProtocolClasses())
            ->map(function ($class) {
                try {
                    $reflection = new \ReflectionClass($class);
                    if (!$reflection->isInstantiable()) {
                        return [];
                    }
                    // 'flags' is a public property with a default value in AbstractProtocol
                    $instanceForFlags = $reflection->newInstanceWithoutConstructor();
                    return $instanceForFlags->flags;
                } catch (\ReflectionException $e) {
                    // Log or handle error if a class is problematic
                    report($e);
                    return [];
                }
            })
            ->flatten()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 根据标识匹配合适的协议处理器类名
     *
     * @param string $flag 请求标识
     * @return string|null 协议类名或null
     */
    public function matchProtocolClassName(string $flag): ?string
    {
        // 按照相反顺序，使最新定义的协议有更高优先级
        foreach (array_reverse($this->getProtocolClasses()) as $protocolClassString) {
            try {
                $reflection = new \ReflectionClass($protocolClassString);

                if (!$reflection->isInstantiable() || !$reflection->isSubclassOf(AbstractProtocol::class)) {
                    continue;
                }

                // 'flags' is a public property in AbstractProtocol
                $instanceForFlags = $reflection->newInstanceWithoutConstructor();
                $flags = $instanceForFlags->flags;

                if (collect($flags)->contains(fn($f) => stripos($flag, (string) $f) !== false)) {
                    return $protocolClassString; // 返回类名字符串
                }
            } catch (\ReflectionException $e) {
                report($e); // Consider logging this error
                continue;
            }
        }
        return null;
    }

    /**
     * 根据标识匹配合适的协议处理器实例 (原有逻辑，如果还需要的话)
     *
     * @param string $flag 请求标识
     * @param array $user 用户信息
     * @param array $servers 服务器列表
     * @param array $clientInfo 客户端信息
     * @return AbstractProtocol|null
     */
    public function matchProtocol($flag, $user, $servers, $clientInfo = [])
    {
        $protocolClassName = $this->matchProtocolClassName($flag);
        if ($protocolClassName) {
            return $this->makeProtocolInstance($protocolClassName, [
                'user' => $user,
                'servers' => $servers,
                'clientName' => $clientInfo['name'] ?? null,
                'clientVersion' => $clientInfo['version'] ?? null
            ]);
        }
        return null;
    }

    /**
     * 创建协议实例的通用方法，兼容不同版本的Laravel容器
     * 
     * @param string $class 类名
     * @param array $parameters 构造参数
     * @return object 实例
     */
    protected function makeProtocolInstance($class, array $parameters)
    {
        // Laravel's make method can accept an array of parameters as its second argument.
        // These will be used when resolving the class's dependencies.
        return $this->container->make($class, $parameters);
    }
}