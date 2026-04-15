<?php

declare(strict_types=1);

namespace Horde\Mail\Autoconfig\Test\Unit;

use Horde\Mail\Autoconfig\Autoconfig;
use Horde\Mail\Autoconfig\Dns\DnsResolverInterface;
use Horde\Mail\Autoconfig\Driver\DriverInterface;
use Horde\Mail\Autoconfig\Exception\AutoconfigException;
use Horde\Mail\Autoconfig\ServerConfig;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\TlsMode;
use Horde\Mail\Autoconfig\ValidationMode;
use Horde\Mail\Autoconfig\Validation\ValidatorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

#[CoversClass(Autoconfig::class)]
class NewAutoconfigTest extends TestCase
{
    public function testDiscoverCollectsFromAllDrivers(): void
    {
        $imap = new ServerConfig(ServerType::Imap, 'imap.example.com', 993, TlsMode::Tls);
        $smtp = new ServerConfig(ServerType::Submission, 'smtp.example.com', 465, TlsMode::Tls);

        $driver1 = $this->createMock(DriverInterface::class);
        $driver1->method('searchMail')->willReturn([$imap]);
        $driver1->method('searchMsa')->willReturn([]);

        $driver2 = $this->createMock(DriverInterface::class);
        $driver2->method('searchMail')->willReturn([]);
        $driver2->method('searchMsa')->willReturn([$smtp]);

        $autoconfig = new Autoconfig($driver1, $driver2);
        $result = $autoconfig->discover('user@example.com');

        $this->assertSame('user@example.com', $result->email);
        $this->assertSame('example.com', $result->domain);
        $this->assertCount(2, $result->servers);
        $this->assertSame($imap, $result->servers[0]);
        $this->assertSame($smtp, $result->servers[1]);
    }

    public function testDiscoverMsaOnlyCallsSearchMsa(): void
    {
        $smtp = new ServerConfig(ServerType::Submission, 'smtp.example.com', 587, TlsMode::StartTls);

        $driver = $this->createMock(DriverInterface::class);
        $driver->method('searchMsa')->willReturn([$smtp]);
        $driver->expects($this->never())->method('searchMail');

        $autoconfig = new Autoconfig($driver);
        $result = $autoconfig->discoverMsa('user@example.com');

        $this->assertCount(1, $result->servers);
        $this->assertSame(ServerType::Submission, $result->servers[0]->type);
    }

    public function testDiscoverMailOnlyCallsSearchMail(): void
    {
        $imap = new ServerConfig(ServerType::Imap, 'imap.example.com', 993, TlsMode::Tls);

        $driver = $this->createMock(DriverInterface::class);
        $driver->method('searchMail')->willReturn([$imap]);
        $driver->expects($this->never())->method('searchMsa');

        $autoconfig = new Autoconfig($driver);
        $result = $autoconfig->discoverMail('user@example.com');

        $this->assertCount(1, $result->servers);
        $this->assertSame(ServerType::Imap, $result->servers[0]->type);
    }

    public function testDiscoverMailPassesFilterFlags(): void
    {
        $driver = $this->createMock(DriverInterface::class);

        $driver->expects($this->once())
            ->method('searchMail')
            ->with(
                $this->anything(),
                'user@example.com',
                true,
                false,
            )
            ->willReturn([]);

        $autoconfig = new Autoconfig($driver);
        $autoconfig->discoverMail('user@example.com', noImap: true, noPop3: false);
    }

    public function testInvalidEmailThrowsException(): void
    {
        $driver = $this->createMock(DriverInterface::class);


        $autoconfig = new Autoconfig($driver);

        $this->expectException(AutoconfigException::class);
        $autoconfig->discover('not-an-email');
    }

    public function testEmptyDriversReturnEmptyResult(): void
    {
        $autoconfig = new Autoconfig();
        $result = $autoconfig->discover('user@example.com');

        $this->assertCount(0, $result->servers);
        $this->assertSame('user@example.com', $result->email);
    }

    public function testSubdomainsAreExtracted(): void
    {
        $domainsReceived = [];
        $driver = $this->createMock(DriverInterface::class);

        $driver->method('searchMail')
            ->willReturnCallback(function (array $domains) use (&$domainsReceived): array {
                $domainsReceived = $domains;
                return [];
            });
        $driver->method('searchMsa')->willReturn([]);

        $autoconfig = new Autoconfig($driver);
        $autoconfig->discover('user@sub.example.com');

        $this->assertSame(['sub.example.com', 'example.com'], $domainsReceived);
    }

    public function testWithValidationNextSkipsFailingServers(): void
    {
        $good = new ServerConfig(ServerType::Imap, 'imap.example.com', 993, TlsMode::Tls);
        $bad  = new ServerConfig(ServerType::Pop3, 'pop.example.com', 995, TlsMode::Tls);

        $driver = $this->createMock(DriverInterface::class);

        $driver->method('searchMail')->willReturn([$good, $bad]);
        $driver->method('searchMsa')->willReturn([]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')
            ->willReturnCallback(fn(ServerConfig $s): bool => $s === $good);

        $autoconfig = (new Autoconfig($driver))
            ->withValidation($validator, ValidationMode::Next);

        $result = $autoconfig->discover('user@example.com');

        $this->assertCount(1, $result->servers);
        $this->assertSame($good, $result->servers[0]);
    }

    public function testWithValidationNextAllFailReturnsEmpty(): void
    {
        $server = new ServerConfig(ServerType::Imap, 'imap.example.com', 993, TlsMode::Tls);

        $driver = $this->createMock(DriverInterface::class);

        $driver->method('searchMail')->willReturn([$server]);
        $driver->method('searchMsa')->willReturn([]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(false);

        $autoconfig = (new Autoconfig($driver))
            ->withValidation($validator, ValidationMode::Next);

        $result = $autoconfig->discover('user@example.com');

        $this->assertCount(0, $result->servers);
    }

    public function testWithValidationFatalThrowsOnFailure(): void
    {
        $server = new ServerConfig(ServerType::Imap, 'imap.example.com', 993, TlsMode::Tls);

        $driver = $this->createMock(DriverInterface::class);

        $driver->method('searchMail')->willReturn([$server]);
        $driver->method('searchMsa')->willReturn([]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(false);

        $autoconfig = (new Autoconfig($driver))
            ->withValidation($validator, ValidationMode::Fatal);

        $this->expectException(AutoconfigException::class);
        $this->expectExceptionMessage('imap.example.com:993');
        $autoconfig->discover('user@example.com');
    }

    public function testWithValidationFatalPassesAllServers(): void
    {
        $imap = new ServerConfig(ServerType::Imap, 'imap.example.com', 993, TlsMode::Tls);
        $smtp = new ServerConfig(ServerType::Submission, 'smtp.example.com', 465, TlsMode::Tls);

        $driver = $this->createMock(DriverInterface::class);

        $driver->method('searchMail')->willReturn([$imap]);
        $driver->method('searchMsa')->willReturn([$smtp]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(true);

        $autoconfig = (new Autoconfig($driver))
            ->withValidation($validator, ValidationMode::Fatal);

        $result = $autoconfig->discover('user@example.com');

        $this->assertCount(2, $result->servers);
    }

    public function testWithValidationNoneSkipsValidatorEntirely(): void
    {
        $server = new ServerConfig(ServerType::Imap, 'imap.example.com', 993, TlsMode::Tls);

        $driver = $this->createMock(DriverInterface::class);

        $driver->method('searchMail')->willReturn([$server]);
        $driver->method('searchMsa')->willReturn([]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        $autoconfig = (new Autoconfig($driver))
            ->withValidation($validator, ValidationMode::None);

        $result = $autoconfig->discover('user@example.com');

        $this->assertCount(1, $result->servers);
    }

    public function testWithValidationReturnsNewInstance(): void
    {
        $server = new ServerConfig(ServerType::Imap, 'imap.example.com', 993, TlsMode::Tls);

        $driver = $this->createMock(DriverInterface::class);

        $driver->method('searchMail')->willReturn([$server]);
        $driver->method('searchMsa')->willReturn([]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn(false);

        $original = new Autoconfig($driver);
        $withVal  = $original->withValidation($validator, ValidationMode::Next);

        // Original is unmodified — still returns the server (no validation)
        $this->assertCount(1, $original->discover('user@example.com')->servers);

        // New instance filters it out
        $this->assertCount(0, $withVal->discover('user@example.com')->servers);
    }

    public function testWithValidationDefaultModeIsNext(): void
    {
        $good = new ServerConfig(ServerType::Imap, 'imap.example.com', 993, TlsMode::Tls);
        $bad  = new ServerConfig(ServerType::Pop3, 'pop.example.com', 995, TlsMode::Tls);

        $driver = $this->createMock(DriverInterface::class);

        $driver->method('searchMail')->willReturn([$good, $bad]);
        $driver->method('searchMsa')->willReturn([]);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')
            ->willReturnCallback(fn(ServerConfig $s): bool => $s === $good);

        // No explicit mode — should default to Next (skip failures, no exception)
        $autoconfig = (new Autoconfig($driver))->withValidation($validator);
        $result = $autoconfig->discover('user@example.com');

        $this->assertCount(1, $result->servers);
        $this->assertSame($good, $result->servers[0]);
    }

    public function testWithBuiltinDriversReturnsWorkingInstance(): void
    {
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('querySrv')->willReturn([]);
        $dns->method('resolves')->willReturn(false);

        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);

        $autoconfig = Autoconfig::withBuiltinDrivers($dns, $httpClient, $requestFactory);

        $this->assertInstanceOf(Autoconfig::class, $autoconfig);

        // Should not throw — returns empty result when nothing resolves
        $result = $autoconfig->discover('user@example.com');
        $this->assertSame('user@example.com', $result->email);
        $this->assertSame('example.com', $result->domain);
    }

    public function testWithBuiltinDriversChainedWithValidation(): void
    {
        $dns = $this->createMock(DnsResolverInterface::class);
        $dns->method('querySrv')->willReturn([]);
        $dns->method('resolves')->willReturn(false);

        $httpClient = $this->createMock(ClientInterface::class);
        $requestFactory = $this->createMock(RequestFactoryInterface::class);

        $validator = $this->createMock(ValidatorInterface::class);
        $validator->expects($this->never())->method('validate');

        $autoconfig = Autoconfig::withBuiltinDrivers($dns, $httpClient, $requestFactory)
            ->withValidation($validator, ValidationMode::Next);

        $this->assertInstanceOf(Autoconfig::class, $autoconfig);

        // No servers discovered, so validator is never called
        $result = $autoconfig->discover('user@example.com');
        $this->assertCount(0, $result->servers);
    }
}
