<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig\Driver;

use Horde\Mail\Autoconfig\Dns\DnsResolverInterface;
use Horde\Mail\Autoconfig\ServerConfig;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\TlsMode;

/**
 * Hostname guessing driver — tries common mail server prefixes and keeps
 * only those that resolve in DNS.
 *
 * This is a last-resort heuristic with no formal standard.
 */
class GuessDriver implements DriverInterface
{
    public function __construct(
        private readonly DnsResolverInterface $dns,
    ) {}

    public function searchMsa(array $domains, string $email): array
    {
        $candidates = [];

        foreach ($domains as $domain) {
            $candidates[] = $domain;
            $candidates[] = 'smtp.' . $domain;
            $candidates[] = 'mail.' . $domain;
        }

        return $this->resolveAndBuild($candidates, ServerType::Submission, 587);
    }

    public function searchMail(
        array $domains,
        string $email,
        bool $noImap = false,
        bool $noPop3 = false,
    ): array {
        $out = [];

        foreach ($domains as $domain) {
            if (!$noImap) {
                $imapCandidates = [
                    $domain,
                    'imap.' . $domain,
                    'mail.' . $domain,
                ];
                $out = array_merge(
                    $out,
                    $this->resolveAndBuild($imapCandidates, ServerType::Imap, 143),
                );
            }

            if (!$noPop3) {
                $pop3Candidates = [
                    $domain,
                    'pop.' . $domain,
                    'pop3.' . $domain,
                    'mail.' . $domain,
                ];
                $out = array_merge(
                    $out,
                    $this->resolveAndBuild($pop3Candidates, ServerType::Pop3, 110),
                );
            }
        }

        return $out;
    }

    /**
     * Filter hostnames by DNS resolution and build ServerConfig objects.
     *
     * @param list<string> $hostnames
     *
     * @return list<ServerConfig>
     */
    private function resolveAndBuild(array $hostnames, ServerType $type, int $port): array
    {
        $out = [];
        $seen = [];

        foreach ($hostnames as $host) {
            if (isset($seen[$host])) {
                continue;
            }
            $seen[$host] = true;

            if ($this->dns->resolves($host)) {
                $out[] = new ServerConfig(
                    type: $type,
                    host: $host,
                    port: $port,
                    tls: TlsMode::StartTls,
                );
            }
        }

        return $out;
    }
}
