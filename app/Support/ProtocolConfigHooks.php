<?php

namespace App\Support;

/**
 * Registry of subscription protocol config hooks.
 *
 * Each hook runs after the protocol class finishes building its config document
 * and before YAML/JSON/base64 serialization (see AbstractProtocol::filterConfigBeforeEncode).
 */
final class ProtocolConfigHooks
{
    public const CLASH_META = 'protocol.clashmeta.config.before_encode';

    public const CLASH = 'protocol.clash.config.before_encode';

    public const STASH = 'protocol.stash.config.before_encode';

    public const SINGBOX = 'protocol.singbox.config.before_encode';

    public const GENERAL = 'protocol.general.config.before_encode';

    public const SHADOWROCKET = 'protocol.shadowrocket.config.before_encode';

    public const QUANTUMULTX = 'protocol.quantumultx.config.before_encode';

    public const SURGE = 'protocol.surge.config.before_encode';

    public const SURFBOARD = 'protocol.surfboard.config.before_encode';

    public const LOON = 'protocol.loon.config.before_encode';

    public const SHADOWSOCKS = 'protocol.shadowsocks.config.before_encode';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::CLASH_META,
            self::CLASH,
            self::STASH,
            self::SINGBOX,
            self::GENERAL,
            self::SHADOWROCKET,
            self::QUANTUMULTX,
            self::SURGE,
            self::SURFBOARD,
            self::LOON,
            self::SHADOWSOCKS,
        ];
    }
}
