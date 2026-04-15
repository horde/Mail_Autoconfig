<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig\Test\Unit;

use Horde_Mail_Autoconfig;
use Horde_Mail_Autoconfig_Driver;
use Horde_Mail_Autoconfig_Driver_Guess;
use Horde_Mail_Autoconfig_Driver_Srv;
use Horde_Mail_Autoconfig_Driver_Thunderbird;
use Horde_Mail_Autoconfig_Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Mail_Autoconfig::class)]
class AutoconfigTest extends TestCase
{
    public function testGetDriversReturnsNonEmptyArray(): void
    {
        $drivers = Horde_Mail_Autoconfig::getDrivers();
        $this->assertNotEmpty($drivers);
        $this->assertContainsOnlyInstancesOf(Horde_Mail_Autoconfig_Driver::class, $drivers);
    }

    public function testGetDriversReturnsSortedByPriority(): void
    {
        $drivers = Horde_Mail_Autoconfig::getDrivers();
        $priorities = array_map(
            fn(Horde_Mail_Autoconfig_Driver $d) => $d->priority,
            $drivers,
        );

        $sorted = $priorities;
        sort($sorted, SORT_NUMERIC);
        $this->assertSame($sorted, $priorities);
    }

    public function testGetDriversContainsAllBuiltinDrivers(): void
    {
        $drivers = Horde_Mail_Autoconfig::getDrivers();
        $classes = array_map('get_class', $drivers);

        $this->assertContains('Horde_Mail_Autoconfig_Driver_Srv', $classes);
        $this->assertContains('Horde_Mail_Autoconfig_Driver_Thunderbird', $classes);
        $this->assertContains('Horde_Mail_Autoconfig_Driver_Guess', $classes);
    }

    public function testConstructorAcceptsCustomDrivers(): void
    {
        $driver = new Horde_Mail_Autoconfig_Driver_Guess();
        $autoconfig = new Horde_Mail_Autoconfig(['drivers' => [$driver]]);

        // With no resolvable domains and a mock DNS, this should return false
        // Testing that custom drivers are accepted without error
        $this->assertInstanceOf(Horde_Mail_Autoconfig::class, $autoconfig);
    }

    public function testGetMsaConfigReturnsFalseWhenNoDriversMatch(): void
    {
        $autoconfig = new Horde_Mail_Autoconfig(['drivers' => []]);
        $result = $autoconfig->getMsaConfig('test@example.com');
        $this->assertFalse($result);
    }

    public function testGetMailConfigReturnsFalseWhenNoDriversMatch(): void
    {
        $autoconfig = new Horde_Mail_Autoconfig(['drivers' => []]);
        $result = $autoconfig->getMailConfig('test@example.com');
        $this->assertFalse($result);
    }

    public function testGetMsaConfigThrowsOnInvalidEmail(): void
    {
        $autoconfig = new Horde_Mail_Autoconfig(['drivers' => []]);
        $this->expectException(Horde_Mail_Autoconfig_Exception::class);
        $autoconfig->getMsaConfig('not-an-email');
    }

    public function testGetMailConfigThrowsOnInvalidEmail(): void
    {
        $autoconfig = new Horde_Mail_Autoconfig(['drivers' => []]);
        $this->expectException(Horde_Mail_Autoconfig_Exception::class);
        $autoconfig->getMailConfig('not-an-email');
    }
}
