<?php

declare(strict_types=1);

namespace Horde\Mail\Autoconfig\Test\Unit\Validation;

use Horde\Mail\Autoconfig\ServerConfig;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\TlsMode;
use Horde\Mail\Autoconfig\Validation\NullValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NullValidator::class)]
class NullValidatorTest extends TestCase
{
    public function testAlwaysReturnsTrue(): void
    {
        $validator = new NullValidator();

        $server = new ServerConfig(ServerType::Imap, 'imap.example.com', 993, TlsMode::Tls);
        $this->assertTrue($validator->validate($server, 'user@example.com'));
    }

    public function testReturnsTrueForAnyServerType(): void
    {
        $validator = new NullValidator();

        $imap = new ServerConfig(ServerType::Imap, 'imap.example.com', 993);
        $pop3 = new ServerConfig(ServerType::Pop3, 'pop.example.com', 995);
        $smtp = new ServerConfig(ServerType::Submission, 'smtp.example.com', 465);

        $this->assertTrue($validator->validate($imap, 'a@b.com'));
        $this->assertTrue($validator->validate($pop3, 'a@b.com'));
        $this->assertTrue($validator->validate($smtp, 'a@b.com'));
    }
}
