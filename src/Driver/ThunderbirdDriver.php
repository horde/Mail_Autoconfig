<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig\Driver;

use Exception;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use SimpleXMLElement;
use Horde\Mail\Autoconfig\ServerConfig;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\TlsMode;

/**
 * Mozilla Thunderbird autoconfig / ISPDB discovery driver.
 *
 * Tries the following URLs in order:
 * 1. https://autoconfig.{domain}/mail/config-v1.1.xml?emailaddress={email}
 * 2. https://{domain}/.well-known/autoconfig/mail/config-v1.1.xml
 * 3. https://autoconfig.thunderbird.net/v1.1/{domain}
 *
 * @see https://wiki.mozilla.org/Thunderbird:Autoconfiguration
 */
class ThunderbirdDriver implements DriverInterface
{
    private const ISPDB_BASE = 'https://autoconfig.thunderbird.net/v1.1/';

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
    ) {}

    public function searchMsa(array $domains, string $email): array
    {
        foreach ($domains as $domain) {
            $result = $this->process($domain, 'outgoingServer', ['smtp'], $email);
            if ($result !== []) {
                return $result;
            }
        }

        return [];
    }

    public function searchMail(
        array $domains,
        string $email,
        bool $noImap = false,
        bool $noPop3 = false,
    ): array {
        $types = [];
        if (!$noImap) {
            $types[] = 'imap';
        }
        if (!$noPop3) {
            $types[] = 'pop3';
        }

        foreach ($domains as $domain) {
            $result = $this->process($domain, 'incomingServer', $types, $email);
            if ($result !== []) {
                return $result;
            }
        }

        return [];
    }

    /**
     * Try autoconfig URLs for a domain, parse the XML, and return servers.
     *
     * @param string        $domain Domain to look up.
     * @param string        $tag    XML tag: 'incomingServer' or 'outgoingServer'.
     * @param list<string>  $types  Allowed type attributes (e.g. 'imap', 'smtp').
     * @param string        $email  The full email address.
     *
     * @return list<ServerConfig>
     */
    private function process(string $domain, string $tag, array $types, string $email): array
    {
        $encodedDomain = urlencode($domain);
        $urls = [
            'https://autoconfig.' . $encodedDomain . '/mail/config-v1.1.xml?emailaddress=' . urlencode($email),
            'https://' . $encodedDomain . '/.well-known/autoconfig/mail/config-v1.1.xml',
            self::ISPDB_BASE . $encodedDomain,
        ];

        foreach ($urls as $url) {
            try {
                $request = $this->requestFactory->createRequest('GET', $url);
                $response = $this->httpClient->sendRequest($request);

                if ($response->getStatusCode() === 404) {
                    continue;
                }

                $body = (string) $response->getBody();
            } catch (Exception) {
                continue;
            }

            try {
                $xml = new SimpleXMLElement($body);
            } catch (Exception) {
                continue;
            }

            $label = (string) ($xml->emailProvider->displayName ?? '');
            $servers = $this->parseServers($xml, $tag, $types, $email, $label);

            if ($servers !== []) {
                return $servers;
            }
        }

        return [];
    }

    /**
     * Parse server entries from autoconfig XML.
     *
     * @param SimpleXMLElement $xml
     * @param string           $tag
     * @param list<string>     $types
     * @param string           $email
     * @param string           $label
     *
     * @return list<ServerConfig>
     */
    private function parseServers(
        SimpleXMLElement $xml,
        string $tag,
        array $types,
        string $email,
        string $label,
    ): array {
        $out = [];

        foreach ($xml->emailProvider->{$tag} as $entry) {
            $type = (string) $entry['type'];
            if (!in_array($type, $types, true)) {
                continue;
            }

            $serverType = match ($type) {
                'imap' => ServerType::Imap,
                'pop3' => ServerType::Pop3,
                'smtp' => ServerType::Submission,
                default => null,
            };
            if ($serverType === null) {
                continue;
            }

            $socketType = strtoupper((string) $entry->socketType);
            $tls = match ($socketType) {
                'SSL' => TlsMode::Tls,
                'STARTTLS' => TlsMode::StartTls,
                'PLAIN' => TlsMode::None,
                default => TlsMode::StartTls,
            };

            $username = $this->resolveUsername((string) $entry->username, $email);

            $out[] = new ServerConfig(
                type: $serverType,
                host: (string) $entry->hostname,
                port: (int) (string) $entry->port,
                tls: $tls,
                label: $label !== '' ? $label : null,
                username: $username,
            );
        }

        return $out;
    }

    /**
     * Substitute template placeholders in the username field.
     */
    private function resolveUsername(string $template, string $email): ?string
    {
        if ($template === '') {
            return null;
        }

        $localPart = strstr($email, '@', true);
        if ($localPart === false) {
            $localPart = $email;
        }

        return str_replace(
            ['%EMAILADDRESS%', '%EMAILLOCALPART%'],
            [$email, $localPart],
            $template,
        );
    }
}
