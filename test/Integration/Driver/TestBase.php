<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig\Test\Integration\Driver;

use Horde_Mail_Autoconfig_Driver;
use Horde_Mail_Autoconfig_Server;
use Horde_Mail_Autoconfig_Server_Msa;
use Horde_Mail_Rfc822_Address;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('integration')]
abstract class TestBase extends TestCase
{
    private Horde_Mail_Autoconfig_Driver $driver;

    abstract protected function createDriver(): Horde_Mail_Autoconfig_Driver;

    protected function setUp(): void
    {
        $this->driver = $this->createDriver();
    }

    #[DataProvider('domainProvider')]
    public function testGetMsaConfig(?array $domains): void
    {
        if ($domains === null) {
            $this->markTestSkipped('No test configuration available.');
        }

        $res = $this->driver->msaSearch($domains, [
            'email' => new Horde_Mail_Rfc822_Address('test@example.com'),
        ]);

        $this->assertNotFalse($res);
        $this->assertNotEmpty($res);
        foreach ($res as $val) {
            $this->assertInstanceOf(Horde_Mail_Autoconfig_Server_Msa::class, $val);
        }
    }

    #[DataProvider('domainProvider')]
    public function testGetMailConfig(?array $domains): void
    {
        if ($domains === null) {
            $this->markTestSkipped('No test configuration available.');
        }

        $res = $this->driver->mailSearch($domains, [
            'email' => new Horde_Mail_Rfc822_Address('test@example.com'),
        ]);

        $this->assertNotFalse($res);
        $this->assertNotEmpty($res);
        foreach ($res as $val) {
            $this->assertInstanceOf(Horde_Mail_Autoconfig_Server::class, $val);
        }
    }

    /**
     * @return array<array{0: ?array}>
     */
    public static function domainProvider(): array
    {
        $confFile = __DIR__ . '/../conf.php';
        if (!file_exists($confFile)) {
            return [[null]];
        }

        $conf = [];
        require $confFile;

        if (!empty($conf['mail_autoconfig']['domains'])) {
            return $conf['mail_autoconfig']['domains'];
        }

        return [[null]];
    }
}
