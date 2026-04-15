<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig\Test\Integration;

use Horde_Mail_Autoconfig;
use Horde_Mail_Autoconfig_Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Mail_Autoconfig::class)]
#[Group('integration')]
class AutoconfigTest extends TestCase
{
    private Horde_Mail_Autoconfig $aconfig;

    protected function setUp(): void
    {
        $this->aconfig = new Horde_Mail_Autoconfig();
    }

    #[DataProvider('emailProvider')]
    public function testGetMsaConfigWithoutAuth(?string $email, ?bool $success): void
    {
        if ($email === null) {
            $this->markTestSkipped('No test configuration available.');
        }

        $config = $this->aconfig->getMsaConfig($email);

        if ($success) {
            $this->assertInstanceOf(Horde_Mail_Autoconfig_Server::class, $config);
        } else {
            $this->assertFalse($config);
        }
    }

    #[DataProvider('emailProvider')]
    public function testGetMailConfigWithoutAuth(?string $email, ?bool $success): void
    {
        if ($email === null) {
            $this->markTestSkipped('No test configuration available.');
        }

        $config = $this->aconfig->getMailConfig($email);

        if ($success) {
            $this->assertInstanceOf(Horde_Mail_Autoconfig_Server::class, $config);
        } else {
            $this->assertFalse($config);
        }
    }

    /**
     * @return array<array{0: ?string, 1: ?bool}>
     */
    public static function emailProvider(): array
    {
        $confFile = __DIR__ . '/conf.php';
        if (!file_exists($confFile)) {
            return [[null, null]];
        }

        $conf = [];
        require $confFile;

        $out = [];

        if (!empty($conf['mail_autoconfig']['nonauth_emails'])) {
            foreach ($conf['mail_autoconfig']['nonauth_emails'] as $val) {
                $out[] = [$val, true];
            }
        }

        if (!empty($conf['mail_autoconfig']['nonauth_emails_bad'])) {
            foreach ($conf['mail_autoconfig']['nonauth_emails_bad'] as $val) {
                $out[] = [$val, false];
            }
        }

        return $out ?: [[null, null]];
    }
}
