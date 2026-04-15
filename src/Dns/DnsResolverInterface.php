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
 * Abstraction over DNS queries needed by the autoconfig component.
 *
 * Keeps the library decoupled from concrete resolvers such as NetDNS2.
 */
interface DnsResolverInterface
{
    /**
     * Query SRV records for the given DNS name.
     *
     * @param string $name Fully qualified SRV name (e.g. "_imaps._tcp.example.com").
     *
     * @return list<SrvRecord> Records sorted by priority/weight, or empty if none found.
     */
    public function querySrv(string $name): array;

    /**
     * Check whether a hostname resolves to at least one A or AAAA record.
     *
     * @param string $hostname The hostname to look up.
     */
    public function resolves(string $hostname): bool;
}
