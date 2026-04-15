# Horde Mail_Autoconfig

Mail server auto-discovery library for IMAP, POP3, and SMTP.

## Overview

Horde Mail_Autoconfig discovers mail server configuration (hostnames, ports,
TLS settings) for a given email address. It ships two independent codepaths:

- **PSR-4 Modern API** (`Horde\Mail\Autoconfig\Autoconfig`). Uses typed value objects, PSR-18 HTTP Client, injectable DNS resolver plugins.
- **PSR-0 Legacy API** (`Horde_Mail_Autoconfig`) The backward-compatible option. Uses `Horde_Http_Client` and `NetDNS2\Resolver` directly.

Both codepaths coexist in the same package (`src/` and `lib/`). There is no
forwarding layer between them. Each is a standalone entry point.

It currently supports IMAP, POP3 and SMTP protocols.

## Installation

```bash
composer require horde/mail_autoconfig
```

## Quick Start (PSR-4)

```php
use Horde\Mail\Autoconfig\Autoconfig;
use Horde\Mail\Autoconfig\Dns\NetDns2Resolver;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\ValidationMode;

$dns  = new NetDns2Resolver();
$http = /* any PSR-18 ClientInterface */;
$rf   = /* any PSR-17 RequestFactoryInterface */;

$autoconfig = Autoconfig::withBuiltinDrivers($dns, $http, $rf)
    ->withValidation($myValidator, ValidationMode::Next);

$result = $autoconfig->discover('user@example.com');
$imap   = $result->firstOfType(ServerType::Imap);
```

`withBuiltinDrivers()` wires the three built-in drivers (SRV, Thunderbird
ISPDB, hostname guessing). `withValidation()` adds optional server validation plugins (only interface and null implementation shipped).
Pass your `ValidatorInterface` implementation and a mode:

- **Next** (default): Skip servers that fail validation, keep the rest
- **Fatal**: Throw `AutoconfigException` on the first failure
- **None**: Skip validation entirely

Both methods return new instances; the originals are never modified.
Without `withValidation()`, all discovered servers are returned as-is.

### Narrowed Searches

```php
// Submission (SMTP/MSA) servers only
$msa = $autoconfig->discoverMsa('user@example.com');

// Incoming mail only, skip POP3
$mail = $autoconfig->discoverMail('user@example.com', noPop3: true);
```

### Working with Results

```php
// First IMAP server, or null
$imap = $result->firstOfType(ServerType::Imap);

// All submission servers as a new DiscoveryResult
$submissionOnly = $result->ofType(ServerType::Submission);
```

### Custom Driver Sets

When you need to go beyond the defaults:
- Drop a driver
- add your own
- or wire drivers with non-default options

Use the constructor directly:

```php
use Horde\Mail\Autoconfig\Driver\SrvDriver;
use Horde\Mail\Autoconfig\Driver\ThunderbirdDriver;

// SRV + Thunderbird only, no hostname guessing
$autoconfig = new Autoconfig(
    new SrvDriver($dns),
    new ThunderbirdDriver($http, $rf),
);

// Chaining still works
$validated = $autoconfig->withValidation($myValidator);
```

## Quick Start (PSR-0 Legacy)

```php
$autoconfig = new Horde_Mail_Autoconfig();

// Returns the first valid server or false
$imap = $autoconfig->getMailConfig('user@example.com', [
    'auth' => $password,
]);

$smtp = $autoconfig->getMsaConfig('user@example.com', [
    'auth' => $password,
]);
```

## Discovery Drivers

`withBuiltinDrivers()` registers these drivers in the order shown:

| Driver | Strategy |
|--------|----------|
| `SrvDriver` | RFC 6186 / RFC 8314 DNS SRV records |
| `ThunderbirdDriver` | Mozilla ISPDB autoconfig XML |
| `GuessDriver` | Common hostname patterns + DNS A/AAAA validation |

### SRV Records (RFC 6186 + RFC 8314)

Queries these service names per domain:

| Service | Type | TLS |
|---------|------|-----|
| `_imaps._tcp` | IMAP | Implicit TLS (993) |
| `_imap._tcp` | IMAP | STARTTLS (143) |
| `_pop3s._tcp` | POP3 | Implicit TLS (995) |
| `_pop3._tcp` | POP3 | STARTTLS (110) |
| `_submissions._tcp` | Submission | Implicit TLS (465) |
| `_submission._tcp` | Submission | STARTTLS (587) |

### Thunderbird ISPDB

Tries these URLs in order (all HTTPS):

1. `https://autoconfig.{domain}/mail/config-v1.1.xml?emailaddress={email}`
2. `https://{domain}/.well-known/autoconfig/mail/config-v1.1.xml`
3. `https://autoconfig.thunderbird.net/v1.1/{domain}`

### Hostname Guessing

Tries common prefixes (`smtp.`, `mail.`, `imap.`, `pop.`, `pop3.`) against
each domain and keeps only hostnames that resolve in DNS.

## Key Differences: PSR-4 vs PSR-0

| Aspect | PSR-4 (`src/`) | PSR-0 (`lib/`) |
|--------|---------------|----------------|
| Return type | `DiscoveryResult` (all servers) | First valid server or `false` |
| Validation | Caller's responsibility | Built-in (connects to test) |
| HTTP client | PSR-18 `ClientInterface` | `Horde_Http_Client` |
| DNS resolver | `DnsResolverInterface` (injectable) | `NetDNS2\Resolver` (direct) |
| TLS modes | `TlsMode` enum | `'tls'` string or `null` |
| Server types | `ServerType` enum | Class hierarchy |
| RFC 8314 | `_submissions._tcp` supported | Not supported |
| ISPDB URL | `autoconfig.thunderbird.net` | `autoconfig.thunderbird.net` |

## System Requirements

- PHP ^8.1
- ext-SimpleXML
- `horde/mail` ^3
- `mikepultz/netdns2` ^2.0 for the DNS probes
- A PSR-18 HTTP client and PSR-17 request factory (for `src/` drivers)

## Testing

```bash
# Unit tests
phpunit --testsuite unit

# Full suite (integration tests need test/conf.php)
phpunit
```

## Integrator and Developer Guides

See [doc/UPGRADING.md](doc/UPGRADING.md) for detailed migration instructions
from the PSR-0 API to the PSR-4 API.

See [doc/EXTENDING.md](doc/UPGRADING.md) for how to add validation of found configs or additional check drivers.

## License

LGPL-2.1-only -- see [LICENSE](LICENSE) for details.

## Links

- **GitHub**: https://github.com/horde/Mail_Autoconfig
- **Packagist**: https://packagist.org/packages/horde/mail_autoconfig
- **Issues**: https://github.com/horde/Mail_Autoconfig/issues
