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
use Horde_Mail_Autoconfig_Driver_Srv;
use Horde_Mail_Autoconfig_Server_Imap;
use Horde_Mail_Autoconfig_Server_Msa;
use Horde_Mail_Autoconfig_Server_Pop3;
use Horde_Mail_Rfc822_Address;
use NetDNS2\Exception as DnsException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(Horde_Mail_Autoconfig_Driver_Srv::class)]
class SrvTest extends TestCase
{
    private Horde_Mail_Autoconfig_Driver_Srv $driver;

    protected function setUp(): void
    {
        $this->driver = new Horde_Mail_Autoconfig_Driver_Srv();
    }

    public function testPriorityIsTen(): void
    {
        $this->assertSame(10, $this->driver->priority);
    }

    public function testIsInstanceOfDriver(): void
    {
        $this->assertInstanceOf(Horde_Mail_Autoconfig_Driver::class, $this->driver);
    }

    public function testMsaSearchReturnsFalseOnDnsFailure(): void
    {
        $this->driver->dns = ResolverStub::failing();

        $result = $this->driver->msaSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertFalse($result);
    }

    public function testMailSearchReturnsFalseOnDnsFailure(): void
    {
        $this->driver->dns = ResolverStub::failing();

        $result = $this->driver->mailSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertFalse($result);
    }

    public function testMsaSearchReturnsServerObjectsOnSuccess(): void
    {
        $srvRecord = new stdClass();
        $srvRecord->target = 'smtp.example.com';
        $srvRecord->port = 587;
        $srvRecord->priority = 10;
        $srvRecord->weight = 0;

        $this->driver->dns = new ResolverStub(
            function (string $name, string $type) use ($srvRecord): stdClass {
                if ($name === '_submission._tcp.example.com' && $type === 'SRV') {
                    $response = new stdClass();
                    $response->answer = [$srvRecord];
                    return $response;
                }
                throw new DnsException('not found');
            },
        );

        $result = $this->driver->msaSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Horde_Mail_Autoconfig_Server_Msa::class, $result[0]);
        $this->assertSame('smtp.example.com', $result[0]->host);
        $this->assertSame(587, $result[0]->port);
    }

    public function testMailSearchReturnsImapAndPop3Servers(): void
    {
        $imapRecord = new stdClass();
        $imapRecord->target = 'imap.example.com';
        $imapRecord->port = 143;
        $imapRecord->priority = 10;
        $imapRecord->weight = 0;

        $pop3Record = new stdClass();
        $pop3Record->target = 'pop3.example.com';
        $pop3Record->port = 110;
        $pop3Record->priority = 20;
        $pop3Record->weight = 0;

        $this->driver->dns = new ResolverStub(
            function (string $name, string $type) use ($imapRecord, $pop3Record): stdClass {
                $response = new stdClass();
                if (str_starts_with($name, '_imap.')) {
                    $response->answer = [$imapRecord];
                    return $response;
                }
                if (str_starts_with($name, '_pop3.')) {
                    $response->answer = [$pop3Record];
                    return $response;
                }
                throw new DnsException('not found');
            },
        );

        $result = $this->driver->mailSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        $classes = array_map('get_class', $result);
        $this->assertContains(Horde_Mail_Autoconfig_Server_Imap::class, $classes);
        $this->assertContains(Horde_Mail_Autoconfig_Server_Pop3::class, $classes);
    }

    public function testMailSearchRespectsNoImapOption(): void
    {
        $pop3Record = new stdClass();
        $pop3Record->target = 'pop3.example.com';
        $pop3Record->port = 110;
        $pop3Record->priority = 10;
        $pop3Record->weight = 0;

        $this->driver->dns = new ResolverStub(
            function (string $name, string $type) use ($pop3Record): stdClass {
                if (str_starts_with($name, '_pop3.') || str_starts_with($name, '_pop3s.')) {
                    $response = new stdClass();
                    $response->answer = [$pop3Record];
                    return $response;
                }
                throw new DnsException('not found');
            },
        );

        $result = $this->driver->mailSearch(
            ['example.com'],
            [
                'email' => new Horde_Mail_Rfc822_Address('test@example.com'),
                'no_imap' => true,
            ],
        );

        $this->assertIsArray($result);
        foreach ($result as $server) {
            $this->assertNotInstanceOf(Horde_Mail_Autoconfig_Server_Imap::class, $server);
        }
    }

    public function testMailSearchRespectsNoPop3Option(): void
    {
        $imapRecord = new stdClass();
        $imapRecord->target = 'imap.example.com';
        $imapRecord->port = 143;
        $imapRecord->priority = 10;
        $imapRecord->weight = 0;

        $this->driver->dns = new ResolverStub(
            function (string $name, string $type) use ($imapRecord): stdClass {
                if (str_starts_with($name, '_imap.') || str_starts_with($name, '_imaps.')) {
                    $response = new stdClass();
                    $response->answer = [$imapRecord];
                    return $response;
                }
                throw new DnsException('not found');
            },
        );

        $result = $this->driver->mailSearch(
            ['example.com'],
            [
                'email' => new Horde_Mail_Rfc822_Address('test@example.com'),
                'no_pop3' => true,
            ],
        );

        $this->assertIsArray($result);
        foreach ($result as $server) {
            $this->assertNotInstanceOf(Horde_Mail_Autoconfig_Server_Pop3::class, $server);
        }
    }

    public function testMsaSearchQueriesSubmissionSrv(): void
    {
        $queriedNames = [];

        $this->driver->dns = new ResolverStub(
            function (string $name, string $type) use (&$queriedNames): never {
                $queriedNames[] = $name;
                throw new DnsException('not found');
            },
        );

        $this->driver->msaSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertContains('_submission._tcp.example.com', $queriedNames);
    }
}
