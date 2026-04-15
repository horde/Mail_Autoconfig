<?php

declare(strict_types=1);

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Mail\Autoconfig;

use Horde\Mail\Autoconfig\Dns\DnsResolverInterface;
use Horde\Mail\Autoconfig\Driver\DriverInterface;
use Horde\Mail\Autoconfig\Driver\GuessDriver;
use Horde\Mail\Autoconfig\Driver\SrvDriver;
use Horde\Mail\Autoconfig\Driver\ThunderbirdDriver;
use Horde\Mail\Autoconfig\Exception\AutoconfigException;
use Horde\Mail\Autoconfig\Validation\NullValidator;
use Horde\Mail\Autoconfig\Validation\ValidatorInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Horde_Mail_Rfc822;
use Horde_Mail_Exception;

/**
 * Main facade for mail server auto-discovery.
 *
 * Iterates registered drivers by priority, collects discovered servers,
 * optionally validates them, and returns the result as an immutable
 * DiscoveryResult.
 */
class Autoconfig implements AutoconfigInterface
{
    /** @var list<DriverInterface> Drivers in the order they were passed. */
    private readonly array $drivers;

    private ValidatorInterface $validator;

    private ValidationMode $validationMode;

    /**
     * @param DriverInterface ...$drivers Discovery drivers (tried in the order given).
     */
    public function __construct(DriverInterface ...$drivers)
    {
        $this->drivers = $drivers;
        $this->validator = new NullValidator();
        $this->validationMode = ValidationMode::None;
    }

    /**
     * Create an instance pre-wired with the three built-in drivers
     * (SRV, Thunderbird ISPDB, hostname guessing).
     *
     * This is the recommended entry point for most integrators.
     * Use the regular constructor when you need a custom driver set.
     */
    public static function withBuiltinDrivers(
        DnsResolverInterface $dns,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
    ): static {
        return new static(
            new SrvDriver($dns),
            new ThunderbirdDriver($httpClient, $requestFactory),
            new GuessDriver($dns),
        );
    }

    public function withValidation(
        ValidatorInterface $validator,
        ValidationMode $mode = ValidationMode::Next,
    ): static {
        $clone = clone $this;
        $clone->validator = $validator;
        $clone->validationMode = $mode;

        return $clone;
    }

    public function discover(string $email): DiscoveryResult
    {
        [$domains, $primaryDomain] = $this->parseEmail($email);

        $servers = [];
        foreach ($this->drivers as $driver) {
            $servers = array_merge(
                $servers,
                $driver->searchMail($domains, $email),
                $driver->searchMsa($domains, $email),
            );
        }

        return new DiscoveryResult($this->applyValidation($servers, $email), $email, $primaryDomain);
    }

    public function discoverMsa(string $email): DiscoveryResult
    {
        [$domains, $primaryDomain] = $this->parseEmail($email);

        $servers = [];
        foreach ($this->drivers as $driver) {
            $servers = array_merge($servers, $driver->searchMsa($domains, $email));
        }

        return new DiscoveryResult($this->applyValidation($servers, $email), $email, $primaryDomain);
    }

    public function discoverMail(
        string $email,
        bool $noImap = false,
        bool $noPop3 = false,
    ): DiscoveryResult {
        [$domains, $primaryDomain] = $this->parseEmail($email);

        $servers = [];
        foreach ($this->drivers as $driver) {
            $servers = array_merge(
                $servers,
                $driver->searchMail($domains, $email, $noImap, $noPop3),
            );
        }

        return new DiscoveryResult($this->applyValidation($servers, $email), $email, $primaryDomain);
    }

    /**
     * Filter the server list according to the current validation mode.
     *
     * @param list<ServerConfig> $servers
     *
     * @return list<ServerConfig>
     *
     * @throws AutoconfigException In Fatal mode, when a server fails validation.
     */
    private function applyValidation(array $servers, string $email): array
    {
        if ($this->validationMode === ValidationMode::None) {
            return $servers;
        }

        $validated = [];
        foreach ($servers as $server) {
            if ($this->validator->validate($server, $email)) {
                $validated[] = $server;
                continue;
            }

            if ($this->validationMode === ValidationMode::Fatal) {
                throw new AutoconfigException(
                    sprintf('Server validation failed for %s:%d', $server->host, $server->port),
                );
            }
            // Next mode — skip silently.
        }

        return $validated;
    }

    /**
     * Parse the email address and extract the (sub)domain list.
     *
     * @return array{list<string>, string} [domains deepest-first, primary domain]
     *
     * @throws AutoconfigException
     */
    private function parseEmail(string $email): array
    {
        $rfc822 = new Horde_Mail_Rfc822();

        try {
            $alist = $rfc822->parseAddressList($email, ['limit' => 1]);
        } catch (Horde_Mail_Exception $e) {
            throw new AutoconfigException('Could not parse e-mail address: ' . $e->getMessage(), 0, $e);
        }

        $address = $alist[0] ?? null;
        if ($address === null) {
            throw new AutoconfigException('Could not parse e-mail address given.');
        }

        $host = $address->host_idn;
        if (!is_string($host) || $host === '') {
            throw new AutoconfigException('Could not determine domain name from e-mail address given.');
        }

        // Split into subdomains, deepest first.
        $domains = [];
        $parts = explode('.', $host);
        while (count($parts) >= 2) {
            $domains[] = implode('.', $parts);
            array_shift($parts);
        }

        return [$domains, $domains[0] ?? $host];
    }
}
