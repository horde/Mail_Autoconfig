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
use Horde\Mail\Autoconfig\Dns\SrvRecord;
use Horde\Mail\Autoconfig\ServerConfig;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\TlsMode;

/**
 * RFC 6186 + RFC 8314 SRV record lookups for mail server discovery.
 *
 * Queries well-known SRV service names (_imaps._tcp, _imap._tcp,
 * _submission._tcp, _submissions._tcp, etc.) and converts the responses
 * into typed ServerConfig objects.
 */
class SrvDriver implements DriverInterface
{
    public function __construct(
        private readonly DnsResolverInterface $dns,
    ) {}

    public function searchMsa(array $domains, string $email): array
    {
        $queries = [
            '_submissions' => [ServerType::Submission, TlsMode::Tls],
            '_submission'  => [ServerType::Submission, TlsMode::StartTls],
        ];

        return $this->srvSearch($domains, $queries);
    }

    public function searchMail(
        array $domains,
        string $email,
        bool $noImap = false,
        bool $noPop3 = false,
    ): array {
        $queries = [];

        if (!$noImap) {
            $queries['_imaps'] = [ServerType::Imap, TlsMode::Tls];
            $queries['_imap']  = [ServerType::Imap, TlsMode::StartTls];
        }
        if (!$noPop3) {
            $queries['_pop3s'] = [ServerType::Pop3, TlsMode::Tls];
            $queries['_pop3']  = [ServerType::Pop3, TlsMode::StartTls];
        }

        return $this->srvSearch($domains, $queries);
    }

    /**
     * Perform the actual SRV lookups and apply RFC 2782 weight selection.
     *
     * @param list<string>                                       $domains
     * @param array<string, array{ServerType, TlsMode}> $queries Service name → [type, tls]
     *
     * @return list<ServerConfig>
     */
    private function srvSearch(array $domains, array $queries): array
    {
        /** @var array<int, list<array{SrvRecord, ServerType, TlsMode}>> $byPriority */
        $byPriority = [];

        foreach ($domains as $domain) {
            foreach ($queries as $service => [$type, $tls]) {
                $records = $this->dns->querySrv($service . '._tcp.' . $domain);
                foreach ($records as $record) {
                    $byPriority[$record->priority][] = [$record, $type, $tls];
                }
            }
        }

        if ($byPriority === []) {
            return [];
        }

        ksort($byPriority, SORT_NUMERIC);

        $out = [];
        foreach ($byPriority as $group) {
            $ordered = $this->weightSelect($group);
            foreach ($ordered as [$record, $type, $tls]) {
                $out[] = new ServerConfig(
                    type: $type,
                    host: $record->target,
                    port: $record->port,
                    tls: $tls,
                );
            }
        }

        return $out;
    }

    /**
     * RFC 2782 weighted selection within a single priority group.
     *
     * @param list<array{SrvRecord, ServerType, TlsMode}> $entries
     *
     * @return list<array{SrvRecord, ServerType, TlsMode}>
     */
    private function weightSelect(array $entries): array
    {
        if (count($entries) <= 1) {
            return $entries;
        }

        // Move weight-0 entries to the front per RFC 2782.
        $zero = [];
        $rest = [];
        foreach ($entries as $entry) {
            if ($entry[0]->weight === 0) {
                $zero[] = $entry;
            } else {
                $rest[] = $entry;
            }
        }
        $pool = array_merge($zero, $rest);

        $ordered = [];
        while (count($pool) > 1) {
            // Compute running sum.
            $runningSum = 0;
            $sums = [];
            foreach ($pool as $i => $entry) {
                $runningSum += $entry[0]->weight;
                $sums[$i] = $runningSum;
            }

            $rand = mt_rand(0, $runningSum);
            foreach ($sums as $i => $sum) {
                if ($sum >= $rand) {
                    $ordered[] = $pool[$i];
                    unset($pool[$i]);
                    $pool = array_values($pool);
                    break;
                }
            }
        }

        // Last entry remaining.
        $ordered[] = reset($pool);

        return $ordered;
    }
}
