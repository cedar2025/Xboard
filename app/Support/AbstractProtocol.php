<?php

namespace App\Support;

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

        // 服务器过滤逻辑
        $this->servers = $this->filterServersByVersion();
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
        // 如果没有客户端信息，直接返回所有服务器
        if (empty($this->clientName) || empty($this->clientVersion)) {
            return $this->servers;
        }

        // 检查当前客户端是否有特殊配置
        if (!isset($this->protocolRequirements[$this->clientName])) {
            return $this->servers;
        }

        return collect($this->servers)->filter(function ($server) {
            return $this->isCompatible($server);
        })->values()->all();
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
        // 如果该协议没有特定要求，则认为兼容
        if (!isset($this->protocolRequirements[$this->clientName][$serverType])) {
            return true;
        }

        $requirements = $this->protocolRequirements[$this->clientName][$serverType];

        if (isset($requirements['base_version']) && version_compare($this->clientVersion, $requirements['base_version'], '<')) {
            return false;
        }

        // 检查每个路径的版本要求
        foreach ($requirements as $path => $valueRequirements) {
            $actualValue = data_get($server, $path);

            if ($actualValue === null) {
                continue;
            }

            if (isset($valueRequirements[$actualValue])) {
                $requiredVersion = $valueRequirements[$actualValue];
                if (version_compare($this->clientVersion, $requiredVersion, '<')) {
                    return false;
                }
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
}