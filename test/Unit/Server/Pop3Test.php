<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig\Test\Unit\Server;

use Horde_Mail_Autoconfig_Server_Pop3;
use Horde_Mail_Autoconfig_Server;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Mail_Autoconfig_Server_Pop3::class)]
class Pop3Test extends TestCase
{
    public function testDefaultPort(): void
    {
        $server = new Horde_Mail_Autoconfig_Server_Pop3();
        $this->assertSame(110, $server->port);
    }

    public function testDefaultProperties(): void
    {
        $server = new Horde_Mail_Autoconfig_Server_Pop3();
        $this->assertNull($server->host);
        $this->assertNull($server->label);
        $this->assertNull($server->tls);
        $this->assertNull($server->username);
    }

    public function testIsInstanceOfServer(): void
    {
        $server = new Horde_Mail_Autoconfig_Server_Pop3();
        $this->assertInstanceOf(Horde_Mail_Autoconfig_Server::class, $server);
    }
}
