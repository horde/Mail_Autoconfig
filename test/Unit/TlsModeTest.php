<?php

declare(strict_types=1);

namespace Horde\Mail\Autoconfig\Test\Unit;

use Horde\Mail\Autoconfig\TlsMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TlsMode::class)]
class TlsModeTest extends TestCase
{
    public function testCaseValues(): void
    {
        $this->assertSame('tls', TlsMode::Tls->value);
        $this->assertSame('starttls', TlsMode::StartTls->value);
        $this->assertSame('none', TlsMode::None->value);
    }

    public function testCaseCount(): void
    {
        $this->assertCount(3, TlsMode::cases());
    }

    public function testFromString(): void
    {
        $this->assertSame(TlsMode::Tls, TlsMode::from('tls'));
        $this->assertSame(TlsMode::StartTls, TlsMode::from('starttls'));
        $this->assertSame(TlsMode::None, TlsMode::from('none'));
    }

    public function testTryFromInvalid(): void
    {
        $this->assertNull(TlsMode::tryFrom('invalid'));
    }
}
