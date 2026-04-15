<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig\Dns;

/**
 * Immutable value object representing a DNS SRV record.
 */
final readonly class SrvRecord
{
    public function __construct(
        public string $target,
        public int $port,
        public int $priority,
        public int $weight,
    ) {}
}
