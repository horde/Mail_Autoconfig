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
use Horde_Mail_Autoconfig_Driver_Guess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

#[CoversClass(Horde_Mail_Autoconfig_Driver_Guess::class)]
#[Group('integration')]
class GuessTest extends TestBase
{
    protected function createDriver(): Horde_Mail_Autoconfig_Driver
    {
        return new Horde_Mail_Autoconfig_Driver_Guess();
    }
}
