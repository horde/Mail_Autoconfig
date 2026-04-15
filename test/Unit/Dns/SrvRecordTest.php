<?php

declare(strict_types=1);

namespace Horde\Mail\Autoconfig\Test\Unit\Dns;

use Horde\Mail\Autoconfig\Dns\SrvRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SrvRecord::class)]
class SrvRecordTest extends TestCase
{
    public function testProperties(): void
    {
        $record = new SrvRecord(
            target: 'imap.example.com',
            port: 993,
            priority: 10,
            weight: 5,
        );

        $this->assertSame('imap.example.com', $record->target);
        $this->assertSame(993, $record->port);
        $this->assertSame(10, $record->priority);
        $this->assertSame(5, $record->weight);
    }
}
