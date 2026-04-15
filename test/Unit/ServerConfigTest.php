<?php

declare(strict_types=1);

namespace Horde\Mail\Autoconfig\Test\Unit;

use Horde\Mail\Autoconfig\ServerConfig;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\TlsMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerConfig::class)]
class ServerConfigTest extends TestCase
{
    public function testConstructionWithDefaults(): void
    {
        $config = new ServerConfig(
            type: ServerType::Imap,
            host: 'imap.example.com',
            port: 993,
        );

        $this->assertSame(ServerType::Imap, $config->type);
        $this->assertSame('imap.example.com', $config->host);
        $this->assertSame(993, $config->port);
        $this->assertSame(TlsMode::StartTls, $config->tls);
        $this->assertNull($config->label);
        $this->assertNull($config->username);
    }

    public function testConstructionWithAllParameters(): void
    {
        $config = new ServerConfig(
            type: ServerType::Submission,
            host: 'smtp.example.com',
            port: 465,
            tls: TlsMode::Tls,
            label: 'Example Mail',
            username: 'user@example.com',
        );

        $this->assertSame(ServerType::Submission, $config->type);
        $this->assertSame('smtp.example.com', $config->host);
        $this->assertSame(465, $config->port);
        $this->assertSame(TlsMode::Tls, $config->tls);
        $this->assertSame('Example Mail', $config->label);
        $this->assertSame('user@example.com', $config->username);
    }

    public function testPop3Type(): void
    {
        $config = new ServerConfig(
            type: ServerType::Pop3,
            host: 'pop.example.com',
            port: 110,
            tls: TlsMode::None,
        );

        $this->assertSame(ServerType::Pop3, $config->type);
        $this->assertSame(TlsMode::None, $config->tls);
    }
}
