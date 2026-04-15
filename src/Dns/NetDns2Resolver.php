<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig\Dns;

use NetDNS2\Exception as DnsException;
use NetDNS2\Resolver;

/**
 * DnsResolverInterface adapter wrapping NetDNS2\Resolver.
 */
class NetDns2Resolver implements DnsResolverInterface
{
    private Resolver $resolver;

    /**
     * @param array<string, mixed>|null $options Options passed through to the NetDNS2 Resolver constructor.
     */
    public function __construct(?array $options = null)
    {
        $this->resolver = $options !== null
            ? new Resolver($options)
            : new Resolver();
    }

    public function querySrv(string $name): array
    {
        try {
            $response = $this->resolver->query($name, 'SRV');
        } catch (DnsException) {
            return [];
        }

        $records = [];
        foreach ($response->answer as $rr) {
            $target = (string) $rr->target;
            if ($target === '' || $target === '.') {
                continue;
            }

            $records[] = new SrvRecord(
                target: $target,
                port: (int) $rr->port,
                priority: (int) $rr->priority,
                weight: (int) $rr->weight,
            );
        }

        return $records;
    }

    public function resolves(string $hostname): bool
    {
        try {
            $this->resolver->query($hostname, 'A');
            return true;
        } catch (DnsException) {
            // Fall through to AAAA.
        }

        try {
            $this->resolver->query($hostname, 'AAAA');
            return true;
        } catch (DnsException) {
            return false;
        }
    }
}
