<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig\Driver;

use Horde\Mail\Autoconfig\ServerConfig;

/**
 * Contract for a mail server discovery driver.
 *
 * Each driver implements one strategy for locating mail servers
 * (SRV records, Thunderbird ISPDB, hostname guessing, etc.).
 */
interface DriverInterface
{
    /**
     * Search for MSA (submission) servers.
     *
     * @param list<string> $domains Domains to search, deepest subdomain first.
     * @param string       $email   The full email address being looked up.
     *
     * @return list<ServerConfig> Discovered servers in priority order.
     */
    public function searchMsa(array $domains, string $email): array;

    /**
     * Search for incoming mail servers (IMAP / POP3).
     *
     * @param list<string> $domains Domains to search, deepest subdomain first.
     * @param string       $email   The full email address being looked up.
     * @param bool         $noImap  If true, skip IMAP lookups.
     * @param bool         $noPop3  If true, skip POP3 lookups.
     *
     * @return list<ServerConfig> Discovered servers in priority order.
     */
    public function searchMail(
        array $domains,
        string $email,
        bool $noImap = false,
        bool $noPop3 = false,
    ): array;
}
