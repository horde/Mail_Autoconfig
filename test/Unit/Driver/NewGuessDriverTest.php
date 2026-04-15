<?php

declare(strict_types=1);

namespace Horde\Mail\Autoconfig\Test\Unit\Driver;

use Horde\Mail\Autoconfig\Dns\DnsResolverInterface;
use Horde\Mail\Autoconfig\Driver\GuessDriver;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\TlsMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GuessDriver::class)]
class NewGuessDriverTest extends TestCase
{
    public function testSearchMsaHostnamePatterns(): void
    {
        $resolved = [];
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('resolves')
            ->willReturnCallback(function (string $host) use (&$resolved): bool {
                $resolved[] = $host;
                return true;
            });

        $driver = new GuessDriver($dns);
        $servers = $driver->searchMsa(['example.com'], 'user@example.com');

        // Should try: example.com, smtp.example.com, mail.example.com
        $this->assertContains('example.com', $resolved);
        $this->assertContains('smtp.example.com', $resolved);
        $this->assertContains('mail.example.com', $resolved);

        foreach ($servers as $server) {
            $this->assertSame(ServerType::Submission, $server->type);
            $this->assertSame(587, $server->port);
            $this->assertSame(TlsMode::StartTls, $server->tls);
        }
    }

    public function testSearchMailImapAndPop3Patterns(): void
    {
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('resolves')->willReturn(true);

        $driver = new GuessDriver($dns);
        $servers = $driver->searchMail(['example.com'], 'user@example.com');

        $types = array_map(fn($s) => $s->type, $servers);
        $this->assertContains(ServerType::Imap, $types);
        $this->assertContains(ServerType::Pop3, $types);
    }

    public function testSearchMailNoImapSkipsImap(): void
    {
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('resolves')->willReturn(true);

        $driver = new GuessDriver($dns);
        $servers = $driver->searchMail(['example.com'], 'user@example.com', noImap: true);

        foreach ($servers as $server) {
            $this->assertSame(ServerType::Pop3, $server->type);
        }
    }

    public function testSearchMailNoPop3SkipsPop3(): void
    {
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('resolves')->willReturn(true);

        $driver = new GuessDriver($dns);
        $servers = $driver->searchMail(['example.com'], 'user@example.com', noPop3: true);

        foreach ($servers as $server) {
            $this->assertSame(ServerType::Imap, $server->type);
        }
    }

    public function testNonResolvingHostsFiltered(): void
    {
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('resolves')->willReturn(false);

        $driver = new GuessDriver($dns);

        $this->assertSame([], $driver->searchMsa(['example.com'], 'user@example.com'));
        $this->assertSame([], $driver->searchMail(['example.com'], 'user@example.com'));
    }

    public function testDuplicateHostsDeduped(): void
    {
        $resolveCount = 0;
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('resolves')
            ->willReturnCallback(function (string $host) use (&$resolveCount): bool {
                $resolveCount++;
                return $host === 'mail.example.com';
            });

        $driver = new GuessDriver($dns);
        $servers = $driver->searchMsa(['example.com'], 'user@example.com');

        // Only mail.example.com resolves
        $this->assertCount(1, $servers);
        $this->assertSame('mail.example.com', $servers[0]->host);
    }
}
