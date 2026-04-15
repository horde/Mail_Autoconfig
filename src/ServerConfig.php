<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig;

/**
 * Immutable value object representing a discovered mail server configuration.
 *
 * Replaces the legacy Horde_Mail_Autoconfig_Server hierarchy with a single
 * typed object. Server validation (actually connecting) is the caller's
 * responsibility — this object only holds configuration data.
 */
final readonly class ServerConfig
{
    public function __construct(
        public ServerType $type,
        public string $host,
        public int $port,
        public TlsMode $tls = TlsMode::StartTls,
        public ?string $label = null,
        public ?string $username = null,
    ) {}
}
