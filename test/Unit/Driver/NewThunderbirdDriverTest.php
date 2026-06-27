<?php

declare(strict_types=1);

namespace Horde\Mail\Autoconfig\Test\Unit\Driver;

use Horde\Mail\Autoconfig\Driver\ThunderbirdDriver;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\TlsMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

#[CoversClass(ThunderbirdDriver::class)]
class NewThunderbirdDriverTest extends TestCase
{
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->requestFactory->method('createRequest')
            ->willReturn($this->createMock(RequestInterface::class));
    }

    public function testSearchMsaWithSmtpServer(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <clientConfig>
              <emailProvider>
                <displayName>Example Mail</displayName>
                <outgoingServer type="smtp">
                  <hostname>smtp.example.com</hostname>
                  <port>465</port>
                  <socketType>SSL</socketType>
                  <username>%EMAILADDRESS%</username>
                </outgoingServer>
              </emailProvider>
            </clientConfig>
            XML;

        $this->mockHttpResponse(200, $xml);

        $driver = new ThunderbirdDriver($this->httpClient, $this->requestFactory);
        $servers = $driver->searchMsa(['example.com'], 'user@example.com');

        $this->assertCount(1, $servers);
        $this->assertSame(ServerType::Submission, $servers[0]->type);
        $this->assertSame('smtp.example.com', $servers[0]->host);
        $this->assertSame(465, $servers[0]->port);
        $this->assertSame(TlsMode::Tls, $servers[0]->tls);
        $this->assertSame('Example Mail', $servers[0]->label);
        $this->assertSame('user@example.com', $servers[0]->username);
    }

    public function testSearchMailImapServer(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <clientConfig>
              <emailProvider>
                <displayName>Example</displayName>
                <incomingServer type="imap">
                  <hostname>imap.example.com</hostname>
                  <port>993</port>
                  <socketType>SSL</socketType>
                  <username>%EMAILLOCALPART%</username>
                </incomingServer>
              </emailProvider>
            </clientConfig>
            XML;

        $this->mockHttpResponse(200, $xml);

        $driver = new ThunderbirdDriver($this->httpClient, $this->requestFactory);
        $servers = $driver->searchMail(['example.com'], 'user@example.com');

        $this->assertCount(1, $servers);
        $this->assertSame(ServerType::Imap, $servers[0]->type);
        $this->assertSame('imap.example.com', $servers[0]->host);
        $this->assertSame(993, $servers[0]->port);
        $this->assertSame(TlsMode::Tls, $servers[0]->tls);
        $this->assertSame('user', $servers[0]->username);
    }

    public function testSearchMailStartTls(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <clientConfig>
              <emailProvider>
                <displayName>Example</displayName>
                <incomingServer type="imap">
                  <hostname>imap.example.com</hostname>
                  <port>143</port>
                  <socketType>STARTTLS</socketType>
                  <username>%EMAILADDRESS%</username>
                </incomingServer>
              </emailProvider>
            </clientConfig>
            XML;

        $this->mockHttpResponse(200, $xml);

        $driver = new ThunderbirdDriver($this->httpClient, $this->requestFactory);
        $servers = $driver->searchMail(['example.com'], 'user@example.com');

        $this->assertCount(1, $servers);
        $this->assertSame(TlsMode::StartTls, $servers[0]->tls);
    }

    public function testSearchMailPlainSocket(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <clientConfig>
              <emailProvider>
                <displayName>Example</displayName>
                <incomingServer type="pop3">
                  <hostname>pop.example.com</hostname>
                  <port>110</port>
                  <socketType>PLAIN</socketType>
                  <username>%EMAILLOCALPART%</username>
                </incomingServer>
              </emailProvider>
            </clientConfig>
            XML;

        $this->mockHttpResponse(200, $xml);

        $driver = new ThunderbirdDriver($this->httpClient, $this->requestFactory);
        $servers = $driver->searchMail(['example.com'], 'user@example.com');

        $this->assertCount(1, $servers);
        $this->assertSame(TlsMode::None, $servers[0]->tls);
        $this->assertSame(ServerType::Pop3, $servers[0]->type);
    }

    public function testSearchMailNoImapSkipsImap(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <clientConfig>
              <emailProvider>
                <displayName>Example</displayName>
                <incomingServer type="imap">
                  <hostname>imap.example.com</hostname>
                  <port>993</port>
                  <socketType>SSL</socketType>
                  <username>%EMAILADDRESS%</username>
                </incomingServer>
                <incomingServer type="pop3">
                  <hostname>pop.example.com</hostname>
                  <port>995</port>
                  <socketType>SSL</socketType>
                  <username>%EMAILADDRESS%</username>
                </incomingServer>
              </emailProvider>
            </clientConfig>
            XML;

        $this->mockHttpResponse(200, $xml);

        $driver = new ThunderbirdDriver($this->httpClient, $this->requestFactory);
        $servers = $driver->searchMail(['example.com'], 'user@example.com', noImap: true);

        $this->assertCount(1, $servers);
        $this->assertSame(ServerType::Pop3, $servers[0]->type);
    }

    public function testHttpFailureReturnsEmpty(): void
    {
        $exception = new class ('fail') extends RuntimeException implements ClientExceptionInterface {};
        $this->httpClient->method('sendRequest')->willThrowException($exception);

        $driver = new ThunderbirdDriver($this->httpClient, $this->requestFactory);

        $this->assertSame([], $driver->searchMsa(['example.com'], 'user@example.com'));
    }

    public function testHttp404ReturnsEmpty(): void
    {
        $this->mockHttpResponse(404, '');

        $driver = new ThunderbirdDriver($this->httpClient, $this->requestFactory);

        $this->assertSame([], $driver->searchMsa(['example.com'], 'user@example.com'));
    }

    public function testInvalidXmlReturnsEmpty(): void
    {
        $this->mockHttpResponse(200, 'not xml at all');

        $driver = new ThunderbirdDriver($this->httpClient, $this->requestFactory);

        $this->assertSame([], $driver->searchMsa(['example.com'], 'user@example.com'));
    }

    public function testEmptyUsernameFieldYieldsNull(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0"?>
            <clientConfig>
              <emailProvider>
                <displayName>Example</displayName>
                <outgoingServer type="smtp">
                  <hostname>smtp.example.com</hostname>
                  <port>587</port>
                  <socketType>STARTTLS</socketType>
                  <username></username>
                </outgoingServer>
              </emailProvider>
            </clientConfig>
            XML;

        $this->mockHttpResponse(200, $xml);

        $driver = new ThunderbirdDriver($this->httpClient, $this->requestFactory);
        $servers = $driver->searchMsa(['example.com'], 'user@example.com');

        $this->assertCount(1, $servers);
        $this->assertNull($servers[0]->username);
    }

    private function mockHttpResponse(int $statusCode, string $body): void
    {
        $stream = $this->createMock(StreamInterface::class);
        $stream->method('__toString')->willReturn($body);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($statusCode);
        $response->method('getBody')->willReturn($stream);

        $this->httpClient->method('sendRequest')->willReturn($response);
    }
}
