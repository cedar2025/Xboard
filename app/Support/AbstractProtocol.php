<?php

namespace App\Support;

use App\Services\Plugin\HookManager;

abstract class AbstractProtocol
{
    /**
     * @var array 用户信息
     */
    protected $user;

    /**
     * @var array 服务器信息
     */
    protected $servers;

    /**
     * @var string|null 客户端名称
     */
    protected $clientName;

    /**
     * @var string|null 客户端版本
     */
    protected $clientVersion;

    /**
     * @var array 协议标识
     */
    public $flags = [];

    /**
     * @var array 协议需求配置
     */
    protected $protocolRequirements = [];

    /**
     * @var array 允许的协议类型（白名单） 为空则不进行过滤
     */
    protected $allowedProtocols = [];

    /**
     * 构造函数
     *
     * @param array $user 用户信息
     * @param array $servers 服务器信息
     * @param string|null $clientName 客户端名称
     * @param string|null $clientVersion 客户端版本
     */
    public function __construct($user, $servers, $clientName = null, $clientVersion = null)
    {
        $this->user = $user;
        $this->servers = $servers;
        $this->clientName = $clientName;
        $this->clientVersion = $clientVersion;
        $this->protocolRequirements = $this->normalizeProtocolRequirements($this->protocolRequirements);
        $this->servers = HookManager::filter('protocol.servers.filtered', $this->filterServersByVersion());
    }

    /**
     * 获取协议标识
     *
     * @return array
     */
    public function getFlags(): array
    {
        return $this->flags;
    }

    /**
     * 处理请求
     *
     * @return mixed
     */
    abstract public function handle();

    /**
     * 根据客户端版本过滤不兼容的服务器
     *
     * @return array
     */
    protected function filterServersByVersion()
    {
        $this->filterByAllowedProtocols();
        $hasGlobalConfig = isset($this->protocolRequirements['*']);
        $hasClientConfig = isset($this->protocolRequirements[$this->clientName]);

        if ((blank($this->clientName) || blank($this->clientVersion)) && !$hasGlobalConfig) {
            return $this->servers;
        }

        if (!$hasGlobalConfig && !$hasClientConfig) {
            return $this->servers;
        }

        return collect($this->servers)
            ->filter(fn($server) => $this->isCompatible($server))
            ->values()
            ->all();
    }

    /**
     * 检查服务器是否与当前客户端兼容
     *
     * @param array $server 服务器信息
     * @return bool
     */
    protected function isCompatible($server)
    {
        $serverType = $server['type'] ?? null;
        if (isset($this->protocolRequirements['*'][$serverType])) {
            $globalRequirements = $this->protocolRequirements['*'][$serverType];
            if (!$this->checkRequirements($globalRequirements, $server)) {
                return false;
            }
        }

        if (!isset($this->protocolRequirements[$this->clientName][$serverType])) {
            return true;
        }

        $requirements = $this->protocolRequirements[$this->clientName][$serverType];
        return $this->checkRequirements($requirements, $server);
    }

    /**
     * 检查版本要求
     *
     * @param array $requirements 要求配置
     * @param array $server 服务器信息
     * @return bool
     */
    private function checkRequirements(array $requirements, array $server): bool
    {
        foreach ($requirements as $field => $filterRule) {
            if (in_array($field, ['base_version', 'incompatible'])) {
                continue;
            }

            $actualValue = data_get($server, $field);

            if (is_array($filterRule) && isset($filterRule['whitelist'])) {
                $allowedValues = $filterRule['whitelist'];
                $strict = $filterRule['strict'] ?? false;
                if ($strict) {
                    if ($actualValue === null) {
                        return false;
                    }
                    if (!is_string($actualValue) && !is_int($actualValue)) {
                        return false;
                    }
                    if (!isset($allowedValues[$actualValue])) {
                        return false;
                    }
                    $requiredVersion = $allowedValues[$actualValue];
                    if ($requiredVersion !== '0.0.0' && version_compare($this->clientVersion, $requiredVersion, '<')) {
                        return false;
                    }
                    continue;
                }
            } else {
                $allowedValues = $filterRule;
                $strict = false;
            }

            if ($actualValue === null) {
                continue;
            }
            if (!is_string($actualValue) && !is_int($actualValue)) {
                continue;
            }
            if (!isset($allowedValues[$actualValue])) {
                continue;
            }
            $requiredVersion = $allowedValues[$actualValue];
            if ($requiredVersion !== '0.0.0' && version_compare($this->clientVersion, $requiredVersion, '<')) {
                return false;
            }
        }

        return true;
    }

    /**
     * 检查当前客户端是否支持特定功能
     *
     * @param string $clientName 客户端名称
     * @param string $minVersion 最低版本要求
     * @param array $additionalConditions 额外条件检查
     * @return bool
     */
    protected function supportsFeature(string $clientName, string $minVersion, array $additionalConditions = []): bool
    {
        // 检查客户端名称
        if ($this->clientName !== $clientName) {
            return false;
        }

        // 检查版本号
        if (empty($this->clientVersion) || version_compare($this->clientVersion, $minVersion, '<')) {
            return false;
        }

        // 检查额外条件
        foreach ($additionalConditions as $condition) {
            if (!$condition) {
                return false;
            }
        }

        return true;
    }

    /**
     * 根据白名单过滤服务器
     *
     * @return void
     */
    protected function filterByAllowedProtocols(): void
    {
        if (!empty($this->allowedProtocols)) {
            $this->servers = collect($this->servers)
                ->filter(fn($server) => in_array($server['type'], $this->allowedProtocols))
                ->values()
                ->all();
        }
    }

    /**
     * 将平铺的协议需求转换为树形结构
     *
     * @param array $flat 平铺的协议需求
     * @return array 树形结构的协议需求
     */
    protected function normalizeProtocolRequirements(array $flat): array
    {
        $result = [];
        foreach ($flat as $key => $value) {
            if (!str_contains($key, '.')) {
                $result[$key] = $value;
                continue;
            }
            $segments = explode('.', $key, 3);
            if (count($segments) < 3) {
                $result[$segments[0]][$segments[1] ?? '*'][''] = $value;
                continue;
            }
            [$client, $type, $field] = $segments;
            $result[$client][$type][$field] = $value;
        }
        return $result;
    }
}