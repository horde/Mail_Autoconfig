<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig\Test\Unit\Driver;

use Horde_Http_Client;
use Horde_Http_Exception;
use Horde_Http_Response_Base;
use Horde_Mail_Autoconfig_Driver;
use Horde_Mail_Autoconfig_Driver_Thunderbird;
use Horde_Mail_Autoconfig_Server_Imap;
use Horde_Mail_Autoconfig_Server_Msa;
use Horde_Mail_Autoconfig_Server_Pop3;
use Horde_Mail_Rfc822_Address;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Horde_Mail_Autoconfig_Driver_Thunderbird::class)]
class ThunderbirdTest extends TestCase
{
    private Horde_Mail_Autoconfig_Driver_Thunderbird $driver;

    protected function setUp(): void
    {
        $this->driver = new Horde_Mail_Autoconfig_Driver_Thunderbird();
    }

    public function testPriorityIsTwenty(): void
    {
        $this->assertSame(20, $this->driver->priority);
    }

    public function testIsInstanceOfDriver(): void
    {
        $this->assertInstanceOf(Horde_Mail_Autoconfig_Driver::class, $this->driver);
    }

    public function testMsaSearchReturnsFalseOnHttpFailure(): void
    {
        $http = $this->createMock(Horde_Http_Client::class);
        $http->method('get')
            ->willThrowException(new Horde_Http_Exception('connection failed'));
        $this->driver->http = $http;

        $result = $this->driver->msaSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertFalse($result);
    }

    public function testMailSearchReturnsFalseOnHttpFailure(): void
    {
        $http = $this->createMock(Horde_Http_Client::class);
        $http->method('get')
            ->willThrowException(new Horde_Http_Exception('connection failed'));
        $this->driver->http = $http;

        $result = $this->driver->mailSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertFalse($result);
    }

    public function testMsaSearchReturnsFalseOn404(): void
    {
        $response = $this->createMock(Horde_Http_Response_Base::class);
        $response->code = 404;

        $http = $this->createMock(Horde_Http_Client::class);
        $http->method('get')->willReturn($response);
        $this->driver->http = $http;

        $result = $this->driver->msaSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertFalse($result);
    }

    public function testMsaSearchParsesXmlResponse(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <clientConfig version="1.1">
              <emailProvider id="example.com">
                <displayName>Example Mail</displayName>
                <outgoingServer type="smtp">
                  <hostname>smtp.example.com</hostname>
                  <port>587</port>
                  <socketType>STARTTLS</socketType>
                  <username>%EMAILADDRESS%</username>
                </outgoingServer>
              </emailProvider>
            </clientConfig>
            XML;

        $response = $this->createMock(Horde_Http_Response_Base::class);
        $response->code = 200;
        $response->method('getBody')->willReturn($xml);

        $http = $this->createMock(Horde_Http_Client::class);
        $http->method('get')->willReturn($response);
        $this->driver->http = $http;

        $result = $this->driver->msaSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertInstanceOf(Horde_Mail_Autoconfig_Server_Msa::class, $result[0]);
        $this->assertSame('smtp.example.com', $result[0]->host);
        $this->assertSame(587, $result[0]->port);
        $this->assertSame('Example Mail', $result[0]->label);
    }

    public function testMailSearchParsesIncomingServers(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <clientConfig version="1.1">
              <emailProvider id="example.com">
                <displayName>Example Mail</displayName>
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

        $response = $this->createMock(Horde_Http_Response_Base::class);
        $response->code = 200;
        $response->method('getBody')->willReturn($xml);

        $http = $this->createMock(Horde_Http_Client::class);
        $http->method('get')->willReturn($response);
        $this->driver->http = $http;

        $result = $this->driver->mailSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertIsArray($result);
        $this->assertCount(2, $result);

        $classes = array_map('get_class', $result);
        $this->assertContains(Horde_Mail_Autoconfig_Server_Imap::class, $classes);
        $this->assertContains(Horde_Mail_Autoconfig_Server_Pop3::class, $classes);
    }

    public function testMailSearchRespectsNoImapOption(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <clientConfig version="1.1">
              <emailProvider id="example.com">
                <displayName>Example Mail</displayName>
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

        $response = $this->createMock(Horde_Http_Response_Base::class);
        $response->code = 200;
        $response->method('getBody')->willReturn($xml);

        $http = $this->createMock(Horde_Http_Client::class);
        $http->method('get')->willReturn($response);
        $this->driver->http = $http;

        $result = $this->driver->mailSearch(
            ['example.com'],
            [
                'email' => new Horde_Mail_Rfc822_Address('test@example.com'),
                'no_imap' => true,
            ],
        );

        $this->assertIsArray($result);
        foreach ($result as $server) {
            $this->assertNotInstanceOf(Horde_Mail_Autoconfig_Server_Imap::class, $server);
        }
    }

    public function testMailSearchRespectsNoPop3Option(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <clientConfig version="1.1">
              <emailProvider id="example.com">
                <displayName>Example Mail</displayName>
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

        $response = $this->createMock(Horde_Http_Response_Base::class);
        $response->code = 200;
        $response->method('getBody')->willReturn($xml);

        $http = $this->createMock(Horde_Http_Client::class);
        $http->method('get')->willReturn($response);
        $this->driver->http = $http;

        $result = $this->driver->mailSearch(
            ['example.com'],
            [
                'email' => new Horde_Mail_Rfc822_Address('test@example.com'),
                'no_pop3' => true,
            ],
        );

        $this->assertIsArray($result);
        foreach ($result as $server) {
            $this->assertNotInstanceOf(Horde_Mail_Autoconfig_Server_Pop3::class, $server);
        }
    }

    public function testUsernameSubstitution(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <clientConfig version="1.1">
              <emailProvider id="example.com">
                <displayName>Example Mail</displayName>
                <outgoingServer type="smtp">
                  <hostname>smtp.example.com</hostname>
                  <port>587</port>
                  <socketType>STARTTLS</socketType>
                  <username>%EMAILADDRESS%</username>
                </outgoingServer>
              </emailProvider>
            </clientConfig>
            XML;

        $response = $this->createMock(Horde_Http_Response_Base::class);
        $response->code = 200;
        $response->method('getBody')->willReturn($xml);

        $http = $this->createMock(Horde_Http_Client::class);
        $http->method('get')->willReturn($response);
        $this->driver->http = $http;

        $result = $this->driver->msaSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('user@example.com')],
        );

        $this->assertIsArray($result);
        $this->assertSame('user@example.com', $result[0]->username);
    }

    public function testUsernameSubstitutionLocalPart(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <clientConfig version="1.1">
              <emailProvider id="example.com">
                <displayName>Example Mail</displayName>
                <outgoingServer type="smtp">
                  <hostname>smtp.example.com</hostname>
                  <port>587</port>
                  <socketType>STARTTLS</socketType>
                  <username>%EMAILLOCALPART%</username>
                </outgoingServer>
              </emailProvider>
            </clientConfig>
            XML;

        $response = $this->createMock(Horde_Http_Response_Base::class);
        $response->code = 200;
        $response->method('getBody')->willReturn($xml);

        $http = $this->createMock(Horde_Http_Client::class);
        $http->method('get')->willReturn($response);
        $this->driver->http = $http;

        $result = $this->driver->msaSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('user@example.com')],
        );

        $this->assertIsArray($result);
        $this->assertSame('user', $result[0]->username);
    }

    public function testSslSocketTypeSetsCorrectTls(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <clientConfig version="1.1">
              <emailProvider id="example.com">
                <displayName>Example Mail</displayName>
                <incomingServer type="imap">
                  <hostname>imap.example.com</hostname>
                  <port>993</port>
                  <socketType>SSL</socketType>
                  <username>%EMAILADDRESS%</username>
                </incomingServer>
              </emailProvider>
            </clientConfig>
            XML;

        $response = $this->createMock(Horde_Http_Response_Base::class);
        $response->code = 200;
        $response->method('getBody')->willReturn($xml);

        $http = $this->createMock(Horde_Http_Client::class);
        $http->method('get')->willReturn($response);
        $this->driver->http = $http;

        $result = $this->driver->mailSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('test@example.com')],
        );

        $this->assertIsArray($result);
        $this->assertSame('tls', $result[0]->tls);
    }

    public function testEmptyUsernameTemplateDoesNotSetUsername(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <clientConfig version="1.1">
              <emailProvider id="example.com">
                <displayName>Example Mail</displayName>
                <outgoingServer type="smtp">
                  <hostname>smtp.example.com</hostname>
                  <port>587</port>
                  <socketType>STARTTLS</socketType>
                  <username></username>
                </outgoingServer>
              </emailProvider>
            </clientConfig>
            XML;

        $response = $this->createMock(Horde_Http_Response_Base::class);
        $response->code = 200;
        $response->method('getBody')->willReturn($xml);

        $http = $this->createMock(Horde_Http_Client::class);
        $http->method('get')->willReturn($response);
        $this->driver->http = $http;

        $result = $this->driver->msaSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('user@example.com')],
        );

        $this->assertIsArray($result);
        $this->assertNull($result[0]->username);
    }

    public function testNonSslSocketTypeDoesNotSetTls(): void
    {
        $xml = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <clientConfig version="1.1">
              <emailProvider id="example.com">
                <displayName>Example Mail</displayName>
                <outgoingServer type="smtp">
                  <hostname>smtp.example.com</hostname>
                  <port>587</port>
                  <socketType>STARTTLS</socketType>
                  <username>%EMAILADDRESS%</username>
                </outgoingServer>
              </emailProvider>
            </clientConfig>
            XML;

        $response = $this->createMock(Horde_Http_Response_Base::class);
        $response->code = 200;
        $response->method('getBody')->willReturn($xml);

        $http = $this->createMock(Horde_Http_Client::class);
        $http->method('get')->willReturn($response);
        $this->driver->http = $http;

        $result = $this->driver->msaSearch(
            ['example.com'],
            ['email' => new Horde_Mail_Rfc822_Address('user@example.com')],
        );

        $this->assertIsArray($result);
        $this->assertNull($result[0]->tls);
    }
}
