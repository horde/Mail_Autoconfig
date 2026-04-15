<?php

declare(strict_types=1);

namespace Horde\Mail\Autoconfig\Test\Unit;

use Horde\Mail\Autoconfig\DiscoveryResult;
use Horde\Mail\Autoconfig\ServerConfig;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\TlsMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiscoveryResult::class)]
class DiscoveryResultTest extends TestCase
{
    private ServerConfig $imap;
    private ServerConfig $pop3;
    private ServerConfig $smtp;

    protected function setUp(): void
    {
        $this->imap = new ServerConfig(ServerType::Imap, 'imap.example.com', 993, TlsMode::Tls);
        $this->pop3 = new ServerConfig(ServerType::Pop3, 'pop.example.com', 995, TlsMode::Tls);
        $this->smtp = new ServerConfig(ServerType::Submission, 'smtp.example.com', 465, TlsMode::Tls);
    }

    public function testProperties(): void
    {
        $result = new DiscoveryResult(
            [$this->imap, $this->pop3, $this->smtp],
            'user@example.com',
            'example.com',
        );

        $this->assertCount(3, $result->servers);
        $this->assertSame('user@example.com', $result->email);
        $this->assertSame('example.com', $result->domain);
    }

    public function testFirstOfTypeFound(): void
    {
        $result = new DiscoveryResult([$this->imap, $this->pop3, $this->smtp], 'user@example.com', 'example.com');

        $this->assertSame($this->imap, $result->firstOfType(ServerType::Imap));
        $this->assertSame($this->pop3, $result->firstOfType(ServerType::Pop3));
        $this->assertSame($this->smtp, $result->firstOfType(ServerType::Submission));
    }

    public function testFirstOfTypeNotFound(): void
    {
        $result = new DiscoveryResult([$this->imap], 'user@example.com', 'example.com');

        $this->assertNull($result->firstOfType(ServerType::Pop3));
    }

    public function testOfType(): void
    {
        $imap2 = new ServerConfig(ServerType::Imap, 'imap2.example.com', 143, TlsMode::StartTls);
        $result = new DiscoveryResult(
            [$this->imap, $this->pop3, $imap2, $this->smtp],
            'user@example.com',
            'example.com',
        );

        $imapOnly = $result->ofType(ServerType::Imap);
        $this->assertCount(2, $imapOnly->servers);
        $this->assertSame($this->imap, $imapOnly->servers[0]);
        $this->assertSame($imap2, $imapOnly->servers[1]);
        $this->assertSame('user@example.com', $imapOnly->email);
        $this->assertSame('example.com', $imapOnly->domain);
    }

    public function testOfTypeEmpty(): void
    {
        $result = new DiscoveryResult([$this->imap], 'user@example.com', 'example.com');

        $pop3Only = $result->ofType(ServerType::Pop3);
        $this->assertCount(0, $pop3Only->servers);
    }

    public function testEmptyResult(): void
    {
        $result = new DiscoveryResult([], 'user@example.com', 'example.com');

        $this->assertCount(0, $result->servers);
        $this->assertNull($result->firstOfType(ServerType::Imap));
    }
}
