<?php

declare(strict_types=1);

namespace Horde\Mail\Autoconfig\Test\Unit;

use Horde\Mail\Autoconfig\ServerType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ServerType::class)]
class ServerTypeTest extends TestCase
{
    public function testCaseValues(): void
    {
        $this->assertSame('imap', ServerType::Imap->value);
        $this->assertSame('pop3', ServerType::Pop3->value);
        $this->assertSame('submission', ServerType::Submission->value);
    }

    public function testCaseCount(): void
    {
        $this->assertCount(3, ServerType::cases());
    }

    public function testFromString(): void
    {
        $this->assertSame(ServerType::Imap, ServerType::from('imap'));
        $this->assertSame(ServerType::Pop3, ServerType::from('pop3'));
        $this->assertSame(ServerType::Submission, ServerType::from('submission'));
    }

    public function testTryFromInvalid(): void
    {
        $this->assertNull(ServerType::tryFrom('smtp'));
    }
}
