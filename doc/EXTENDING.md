# Extending Mail_Autoconfig

This document covers how to integrate the PSR-4 API (`src/`) into your
application and how to extend it with custom discovery drivers, validators,
or DNS resolver backends.

## Extension Points

The library has three pluggable interfaces. Each solves one concern:

| Interface | Purpose | Built-in implementations |
|-----------|---------|--------------------------|
| `DriverInterface` | Discover servers for a domain | `SrvDriver`, `ThunderbirdDriver`, `GuessDriver` |
| `ValidatorInterface` | Test whether a discovered server actually works | `NullValidator` |
| `DnsResolverInterface` | Resolve DNS queries | `NetDns2Resolver` |

Everything else (`ServerConfig`, `DiscoveryResult`, the enums) is
intentionally `final readonly`. Consume it, don't extend it.

## Writing a Custom Driver

Implement `Horde\Mail\Autoconfig\Driver\DriverInterface`:

```php
use Horde\Mail\Autoconfig\Driver\DriverInterface;
use Horde\Mail\Autoconfig\ServerConfig;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\TlsMode;

class AutodiscoverDriver implements DriverInterface
{
    public function searchMsa(array $domains, string $email): array
    {
        // Return list<ServerConfig> or [] if nothing found.
        // $domains arrive deepest subdomain first: ['sub.example.com', 'example.com']
        // $email is the full address the user typed.
        return [];
    }

    public function searchMail(
        array $domains,
        string $email,
        bool $noImap = false,
        bool $noPop3 = false,
    ): array {
        foreach ($domains as $domain) {
            $config = $this->queryAutodiscover($domain);
            if ($config !== null) {
                return [new ServerConfig(
                    type: ServerType::Imap,
                    host: $config['host'],
                    port: $config['port'],
                    tls: TlsMode::Tls,
                    username: $email,
                )];
            }
        }

        return [];
    }
}
```

### Guidelines

- **Return early.** Once you find servers for a domain, return them. The
  facade iterates all drivers anyway. You need not try every domain if the
  first one works.
- **Respect `$noImap` / `$noPop3`.** Check these flags before building
  `ServerConfig` objects of that type.
- **Never throw for "not found."** Return `[]` when you have no results. Only
  throw for genuinely unrecoverable errors (invalid credentials to a
  configuration API, etc.).

### Registering a Custom Driver

Pass it to the constructor alongside or instead of the built-in drivers.
Drivers are tried in the order you pass them:

```php
use Horde\Mail\Autoconfig\Autoconfig;
use Horde\Mail\Autoconfig\Dns\NetDns2Resolver;
use Horde\Mail\Autoconfig\Driver\SrvDriver;
use Horde\Mail\Autoconfig\Driver\ThunderbirdDriver;

$dns = new NetDns2Resolver();

// Add your driver alongside the built-ins
$autoconfig = new Autoconfig(
    new SrvDriver($dns),
    new ThunderbirdDriver($httpClient, $requestFactory),
    new AutodiscoverDriver($httpClient),
    // GuessDriver omitted on purpose
);
```

## Writing a Custom Validator

Implement `Horde\Mail\Autoconfig\Validation\ValidatorInterface`:

```php
use Horde\Mail\Autoconfig\ServerConfig;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\Validation\ValidatorInterface;

class ImapSmtpValidator implements ValidatorInterface
{
    public function __construct(
        private readonly string $password,
    ) {}

    public function validate(ServerConfig $server, string $email): bool
    {
        return match ($server->type) {
            ServerType::Imap       => $this->testImap($server, $email),
            ServerType::Pop3       => $this->testPop3($server, $email),
            ServerType::Submission => $this->testSmtp($server, $email),
        };

        // Return true if the server accepted the connection.
        // Return false to skip (Next mode) or abort (Fatal mode).
        // Never throw — return false instead.
    }
}
```

Then chain it:

```php
use Horde\Mail\Autoconfig\ValidationMode;

$autoconfig = Autoconfig::withBuiltinDrivers($dns, $http, $rf)
    ->withValidation(new ImapSmtpValidator($password), ValidationMode::Next);
```

### Guidelines

- **Return `bool`, don't throw.** The facade handles mode logic (skip vs.
  abort). Your validator just answers "does this server work?"
- **Be fast.** The validator is called once per discovered server. Use short
  timeouts. You're probing potentially multiple paths, not transferring mail.
- **Credentials are yours to manage.** The `$email` parameter is passed as a
  convenience for the common case, i.e. the email address doubles as the
  login name. When the actual username differs (AD account, numeric ID, etc.), inject it via your validator's constructor.  Autoconfig was built to avoid limiting assumptions.
  The interface does not restrict what state your implementation carries.

## Writing a Custom DNS Resolver

Implement `Horde\Mail\Autoconfig\Dns\DnsResolverInterface`:

```php
use Horde\Mail\Autoconfig\Dns\DnsResolverInterface;
use Horde\Mail\Autoconfig\Dns\SrvRecord;

class CachingDnsResolver implements DnsResolverInterface
{
    public function __construct(
        private readonly DnsResolverInterface $inner,
        private readonly CacheInterface $cache,
    ) {}

    public function querySrv(string $name): array
    {
        return $this->cache->get($name, fn() => $this->inner->querySrv($name));
    }

    public function resolves(string $hostname): bool
    {
        return $this->cache->get(
            'resolves:' . $hostname,
            fn() => $this->inner->resolves($hostname),
        );
    }
}
```

### Guidelines

- **`querySrv()` returns `list<SrvRecord>`, never throws.** Return `[]` when
  a name does not exist or the query fails.
- **`resolves()` returns `bool`, never throws.** Return `false` on lookup
  failure.
- The interface is deliberately small (two methods). It covers what the
  library actually needs, not the full DNS protocol. If you need MX lookups
  for a custom driver, add that to your driver -- don't widen this interface.

## Integrating with a DI Container

All dependencies are constructor-injected. A typical PSR-11 container
registration:

```php
// Register the infrastructure
$container->set(DnsResolverInterface::class, fn() => new NetDns2Resolver());

$container->set(AutoconfigInterface::class, function ($c) {
    return Autoconfig::withBuiltinDrivers(
        $c->get(DnsResolverInterface::class),
        $c->get(ClientInterface::class),
        $c->get(RequestFactoryInterface::class),
    )->withValidation(
        $c->get(ValidatorInterface::class),
    );
});
```

## What NOT to Do

- **Don't subclass `Autoconfig`.** The facade is not designed for
  inheritance. Use the constructor or `withBuiltinDrivers()` to compose
  behaviour. If you need a fundamentally different discovery strategy,
  implement `AutoconfigInterface` from scratch.
- **Don't subclass `ServerConfig` or the enums.** They are `final readonly`
  for a reason: Downstream code can rely on exhaustive `match` over
  `ServerType` and `TlsMode`. If you need extra metadata, wrap
  `ServerConfig` in your own object rather than trying to extend it.
- **Don't throw exceptions from drivers or validators for "not found"
  conditions.** Return `[]` (drivers) or `false` (validators). Exceptions
  are for broken infrastructure, not missing data.
- **Don't call `searchMsa()` / `searchMail()` directly** unless you're
  building your own facade. The `Autoconfig` facade handles email parsing,
  domain splitting, driver iteration, and validation. Calling driver methods
  directly bypasses all of that.
- **Don't widen `DnsResolverInterface`** for your custom driver's needs. Add
  protocol-specific lookups (MX, TXT, etc.) to your driver's own
  dependencies.
