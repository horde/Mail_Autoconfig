<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig\Test\Unit\Driver;

use Horde_Mail_Autoconfig_Driver;
use Horde_Mail_Autoconfig_Driver_Guess;
use Horde_Mail_Autoconfig_Server_Imap;
use Horde_Mail_Autoconfig_Server_Msa;
use Horde_Mail_Autoconfig_Server_Pop3;
use Horde_Mail_Rfc822_Address;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Horde_Mail_Autoconfig_Driver_Guess::class)]
class GuessTest extends TestCase
{
    private Horde_Mail_Autoconfig_Driver_Guess $driver;

    protected function setUp(): void
    {
        $this->driver = new Horde_Mail_Autoconfig_Driver_Guess();
    }

    public function testPriorityIsThirty(): void
    {
        $this->assertSame(30, $this->driver->priority);
    }

    public function testIsInstanceOfDriver(): void
    {
        $this->assertInstanceOf(Horde_Mail_Autoconfig_Driver::class, $this->driver);
    }

    public function testMsaSearchReturnsFalseWhenNoDnsResolves(): void
    {
        $this->driver->dns = ResolverStub::failing();

        $result = $this->driver->msaSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertFalse($result);
    }

    public function testMailSearchReturnsFalseWhenNoDnsResolves(): void
    {
        $this->driver->dns = ResolverStub::failing();

        $result = $this->driver->mailSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertFalse($result);
    }

    public function testMsaSearchGeneratesCorrectHostnames(): void
    {
        $response = new stdClass();
        $response->answer = [new stdClass()];

        $this->driver->dns = ResolverStub::withAnswer([new stdClass()]);

        $result = $this->driver->msaSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $hosts = array_map(fn($s) => $s->host, $result);
        $this->assertContains('example.com', $hosts);
        $this->assertContains('smtp.example.com', $hosts);
        $this->assertContains('mail.example.com', $hosts);

        foreach ($result as $server) {
            $this->assertInstanceOf(Horde_Mail_Autoconfig_Server_Msa::class, $server);
        }
    }

    public function testMailSearchGeneratesCorrectHostnames(): void
    {
        $this->driver->dns = ResolverStub::withAnswer([new stdClass()]);

        $result = $this->driver->mailSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $hosts = array_map(fn($s) => $s->host, $result);
        $this->assertContains('example.com', $hosts);
        $this->assertContains('imap.example.com', $hosts);
        $this->assertContains('pop.example.com', $hosts);
        $this->assertContains('pop3.example.com', $hosts);
        $this->assertContains('mail.example.com', $hosts);
    }

    public function testMailSearchRespectsNoImapOption(): void
    {
        $this->driver->dns = ResolverStub::withAnswer([new stdClass()]);

        $result = $this->driver->mailSearch(
            ['example.com'],
            [
                'email' => new Horde_Mail_Rfc822_Address('test@example.com'),
                'no_imap' => true,
            ],
        );

        $this->assertIsArray($result);
        $hosts = array_map(fn($s) => $s->host, $result);
        $this->assertNotContains('imap.example.com', $hosts);
    }

    public function testMailSearchRespectsNoPop3Option(): void
    {
        $this->driver->dns = ResolverStub::withAnswer([new stdClass()]);

        $result = $this->driver->mailSearch(
            ['example.com'],
            [
                'email' => new Horde_Mail_Rfc822_Address('test@example.com'),
                'no_pop3' => true,
            ],
        );

        $this->assertIsArray($result);
        $hosts = array_map(fn($s) => $s->host, $result);
        $this->assertNotContains('pop.example.com', $hosts);
        $this->assertNotContains('pop3.example.com', $hosts);
    }

    public function testMsaSearchWithMultipleDomains(): void
    {
        $this->driver->dns = ResolverStub::withAnswer([new stdClass()]);

        $result = $this->driver->msaSearch(
            ['sub.example.com', 'example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@sub.example.com')],
        );

        $this->assertIsArray($result);
        $hosts = array_map(fn($s) => $s->host, $result);
        $this->assertContains('sub.example.com', $hosts);
        $this->assertContains('example.com', $hosts);
        $this->assertContains('smtp.sub.example.com', $hosts);
        $this->assertContains('smtp.example.com', $hosts);
    }
}
