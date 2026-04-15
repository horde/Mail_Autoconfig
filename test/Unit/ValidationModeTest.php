<?php

declare(strict_types=1);

namespace Horde\Mail\Autoconfig\Test\Unit;

use Horde\Mail\Autoconfig\ValidationMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationMode::class)]
class ValidationModeTest extends TestCase
{
    public function testCaseValues(): void
    {
        $this->assertSame('none', ValidationMode::None->value);
        $this->assertSame('next', ValidationMode::Next->value);
        $this->assertSame('fatal', ValidationMode::Fatal->value);
    }

    public function testCaseCount(): void
    {
        $this->assertCount(3, ValidationMode::cases());
    }

    public function testFromString(): void
    {
        $this->assertSame(ValidationMode::None, ValidationMode::from('none'));
        $this->assertSame(ValidationMode::Next, ValidationMode::from('next'));
        $this->assertSame(ValidationMode::Fatal, ValidationMode::from('fatal'));
    }

    public function testTryFromInvalid(): void
    {
        $this->assertNull(ValidationMode::tryFrom('invalid'));
    }
}
