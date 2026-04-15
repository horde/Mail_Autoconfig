<?php

declare(strict_types=1);

namespace Horde\Mail\Autoconfig\Test\Unit\Driver;

use Horde\Mail\Autoconfig\Dns\DnsResolverInterface;
use Horde\Mail\Autoconfig\Dns\SrvRecord;
use Horde\Mail\Autoconfig\Driver\SrvDriver;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\TlsMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SrvDriver::class)]
class NewSrvDriverTest extends TestCase
{
    public function testSearchMsaFindsSubmissionAndSubmissions(): void
    {
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('querySrv')
            ->willReturnCallback(function (string $name): array {
                return match ($name) {
                    '_submissions._tcp.example.com' => [
                        new SrvRecord('smtp.example.com', 465, 10, 0),
                    ],
                    '_submission._tcp.example.com' => [
                        new SrvRecord('smtp.example.com', 587, 20, 0),
                    ],
                    default => [],
                };
            });

        $driver = new SrvDriver($dns);
        $servers = $driver->searchMsa(['example.com'], 'user@example.com');

        $this->assertCount(2, $servers);

        // Priority 10 (_submissions) first
        $this->assertSame('smtp.example.com', $servers[0]->host);
        $this->assertSame(465, $servers[0]->port);
        $this->assertSame(TlsMode::Tls, $servers[0]->tls);
        $this->assertSame(ServerType::Submission, $servers[0]->type);

        // Priority 20 (_submission) second
        $this->assertSame(587, $servers[1]->port);
        $this->assertSame(TlsMode::StartTls, $servers[1]->tls);
    }

    public function testSearchMailImapAndPop3(): void
    {
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('querySrv')
            ->willReturnCallback(function (string $name): array {
                return match ($name) {
                    '_imaps._tcp.example.com' => [
                        new SrvRecord('imap.example.com', 993, 10, 0),
                    ],
                    '_pop3s._tcp.example.com' => [
                        new SrvRecord('pop.example.com', 995, 20, 0),
                    ],
                    default => [],
                };
            });

        $driver = new SrvDriver($dns);
        $servers = $driver->searchMail(['example.com'], 'user@example.com');

        $this->assertCount(2, $servers);
        $this->assertSame(ServerType::Imap, $servers[0]->type);
        $this->assertSame(TlsMode::Tls, $servers[0]->tls);
        $this->assertSame(ServerType::Pop3, $servers[1]->type);
    }

    public function testSearchMailNoImapSkipsImap(): void
    {
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('querySrv')
            ->willReturnCallback(function (string $name): array {
                return match ($name) {
                    '_pop3._tcp.example.com' => [
                        new SrvRecord('pop.example.com', 110, 10, 0),
                    ],
                    default => [],
                };
            });

        $driver = new SrvDriver($dns);
        $servers = $driver->searchMail(['example.com'], 'user@example.com', noImap: true);

        $this->assertCount(1, $servers);
        $this->assertSame(ServerType::Pop3, $servers[0]->type);
    }

    public function testSearchMailNoPop3SkipsPop3(): void
    {
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('querySrv')
            ->willReturnCallback(function (string $name): array {
                return match ($name) {
                    '_imap._tcp.example.com' => [
                        new SrvRecord('imap.example.com', 143, 10, 0),
                    ],
                    default => [],
                };
            });

        $driver = new SrvDriver($dns);
        $servers = $driver->searchMail(['example.com'], 'user@example.com', noPop3: true);

        $this->assertCount(1, $servers);
        $this->assertSame(ServerType::Imap, $servers[0]->type);
    }

    public function testEmptyDnsReturnsEmpty(): void
    {
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('querySrv')->willReturn([]);

        $driver = new SrvDriver($dns);

        $this->assertSame([], $driver->searchMsa(['example.com'], 'user@example.com'));
        $this->assertSame([], $driver->searchMail(['example.com'], 'user@example.com'));
    }

    public function testMultipleDomainsSearched(): void
    {
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('querySrv')
            ->willReturnCallback(function (string $name): array {
                return match ($name) {
                    '_imaps._tcp.sub.example.com' => [
                        new SrvRecord('imap.sub.example.com', 993, 5, 0),
                    ],
                    '_imaps._tcp.example.com' => [
                        new SrvRecord('imap.example.com', 993, 10, 0),
                    ],
                    default => [],
                };
            });

        $driver = new SrvDriver($dns);
        $servers = $driver->searchMail(['sub.example.com', 'example.com'], 'user@sub.example.com');

        $this->assertCount(2, $servers);
        // Lower priority number (5) comes first
        $this->assertSame('imap.sub.example.com', $servers[0]->host);
        $this->assertSame('imap.example.com', $servers[1]->host);
    }
}
