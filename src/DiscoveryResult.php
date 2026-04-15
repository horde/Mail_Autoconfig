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
 * Immutable value object representing the result of a mail server discovery.
 *
 * Contains all servers found across all drivers, the original email address,
 * and the primary domain extracted from it.
 */
final readonly class DiscoveryResult
{
    /**
     * @param list<ServerConfig> $servers Discovered servers in priority order.
     * @param string             $email   The email address that was looked up.
     * @param string             $domain  The primary domain extracted from the email.
     */
    public function __construct(
        public array $servers,
        public string $email,
        public string $domain,
    ) {}

    /**
     * Return the first server of a given type, or null if none found.
     */
    public function firstOfType(ServerType $type): ?ServerConfig
    {
        foreach ($this->servers as $server) {
            if ($server->type === $type) {
                return $server;
            }
        }

        return null;
    }

    /**
     * Return a new DiscoveryResult containing only servers of the given type.
     */
    public function ofType(ServerType $type): self
    {
        $filtered = array_values(
            array_filter(
                $this->servers,
                static fn(ServerConfig $s): bool => $s->type === $type,
            ),
        );

        return new self($filtered, $this->email, $this->domain);
    }
}
