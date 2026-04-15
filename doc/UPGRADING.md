# Upgrading to Mail_Autoconfig 2.0 (PSR-4)

## Overview

Version 2.0 introduces a modern PSR-4 implementation alongside the existing
PSR-0 legacy codebase. The two are independent and there is no forwarding layer and no
shared state. Migrate at your own pace within the lifecycle of Horde 6.
New integrations should use the PSR-4 API though. It's the way forward.

## Requirements

- PHP 8.1 or later
- ext-SimpleXML
- A PSR-18 HTTP client (`psr/http-client ^1.0`)
- A PSR-17 request factory (`psr/http-factory ^1.0`)
- `mikepultz/netdns2 ^2.0` (if using `NetDns2Resolver`)

## What Changed

### Return Type: DiscoveryResult Instead of First-Valid-Server

The PSR-0 facade connects to every candidate server and returns the first one
that responds. The PSR-4 facade returns **all** discovered servers in a
`DiscoveryResult` value object and leaves validation to the caller.

```php
// PSR-0 -- returns Horde_Mail_Autoconfig_Server or false
$server = $autoconfig->getMailConfig('user@example.com', [
    'auth' => $password,
]);

// PSR-4 -- returns DiscoveryResult (never false)
$result = $autoconfig->discoverMail('user@example.com');
$imap   = $result->firstOfType(ServerType::Imap);
// Caller decides how/whether to validate $imap
```

**Why:** Decouples discovery from validation. The library no longer needs
runtime dependencies on `horde/imap_client` or `horde/smtp`.

### Server Objects Replaced by ServerConfig Value Object

The three server subclasses (`Horde_Mail_Autoconfig_Server_Imap`,
`_Pop3`, `_Msa`) with mutable public properties are replaced by a single
immutable `ServerConfig` with a `ServerType` enum:

```php
// PSR-0
$server = new Horde_Mail_Autoconfig_Server_Imap();
$server->host = 'imap.example.com';
$server->port = 993;
$server->tls  = 'tls';

// PSR-4
$server = new ServerConfig(
    type: ServerType::Imap,
    host: 'imap.example.com',
    port: 993,
    tls:  TlsMode::Tls,
);
```

### Options Array Replaced by Named Parameters

```php
// PSR-0
$autoconfig->getMailConfig($email, [
    'no_imap' => true,
    'no_pop3' => false,
]);

// PSR-4
$autoconfig->discoverMail($email, noImap: true, noPop3: false);
```

### HTTP Client: PSR-18 Instead of Horde_Http_Client

The `ThunderbirdDriver` accepts any PSR-18 `ClientInterface` and PSR-17
`RequestFactoryInterface` rather than a concrete `Horde_Http_Client`.

```php
use Horde\Mail\Autoconfig\Driver\ThunderbirdDriver;

$driver = new ThunderbirdDriver($psrHttpClient, $psrRequestFactory);
```

### DNS Resolver: Interface Instead of Final Class

`NetDNS2\Resolver` is `final` and cannot be mocked. The PSR-4 code depends
on `DnsResolverInterface` (two methods: `querySrv()` and `resolves()`). A
ready-made adapter, `NetDns2Resolver`, wraps the real resolver.

```php
use Horde\Mail\Autoconfig\Dns\NetDns2Resolver;
use Horde\Mail\Autoconfig\Driver\SrvDriver;
use Horde\Mail\Autoconfig\Driver\GuessDriver;

$dns = new NetDns2Resolver();
$srv   = new SrvDriver($dns);
$guess = new GuessDriver($dns);
```

For testing, mock `DnsResolverInterface` directly.

### TLS Modes and Server Types: Enums

```php
// PSR-0 -- plain strings
$server->tls = 'tls';   // or null

// PSR-4 -- backed enums
TlsMode::Tls       // 'tls'       -- implicit TLS
TlsMode::StartTls   // 'starttls'  -- upgrade via STARTTLS
TlsMode::None        // 'none'      -- no encryption

ServerType::Imap         // 'imap'
ServerType::Pop3         // 'pop3'
ServerType::Submission   // 'submission'
```

## New Functionality

### RFC 8314: _submissions._tcp SRV Record

The `SrvDriver` now queries `_submissions._tcp` in addition to
`_submission._tcp`. This discovers implicit-TLS submission on port 465 as
recommended by RFC 8314.

### Updated Mozilla ISPDB URL

Both `src/` and `lib/` now use `https://autoconfig.thunderbird.net/v1.1/`
instead of the retired `https://live.mozillamessaging.com/autoconfig/v1.1/`.

### HTTPS-Only Autoconfig URLs

The `ThunderbirdDriver` in `src/` uses HTTPS for all three autoconfig URL
patterns. The `lib/` driver retains the original HTTP URLs for backward
compatibility.

### Optional Server Validation

The PSR-4 facade supports pluggable validation via `withValidation()`.
Inject a `ValidatorInterface` implementation and choose a `ValidationMode`:

- **None** — skip validation entirely (the default when no validator is set).
- **Next** — call the validator for each server; silently drop those that fail.
- **Fatal** — call the validator; throw `AutoconfigException` on the first failure.

```php
use Horde\Mail\Autoconfig\ValidationMode;

$validated = $autoconfig->withValidation($myValidator, ValidationMode::Next);
$result    = $validated->discover('user@example.com');
// $result contains only servers that $myValidator accepted.
```

A `NullValidator` (always returns true) ships as the built-in default.

## Class Name Mapping

| PSR-0 (`lib/`) | PSR-4 (`src/`) |
|-----------------|----------------|
| `Horde_Mail_Autoconfig` | `Horde\Mail\Autoconfig\Autoconfig` |
| `Horde_Mail_Autoconfig_Driver_Srv` | `Horde\Mail\Autoconfig\Driver\SrvDriver` |
| `Horde_Mail_Autoconfig_Driver_Thunderbird` | `Horde\Mail\Autoconfig\Driver\ThunderbirdDriver` |
| `Horde_Mail_Autoconfig_Driver_Guess` | `Horde\Mail\Autoconfig\Driver\GuessDriver` |
| `Horde_Mail_Autoconfig_Server_Imap` | `ServerConfig` with `ServerType::Imap` |
| `Horde_Mail_Autoconfig_Server_Pop3` | `ServerConfig` with `ServerType::Pop3` |
| `Horde_Mail_Autoconfig_Server_Msa` | `ServerConfig` with `ServerType::Submission` |
| `Horde_Mail_Autoconfig_Exception` | `Horde\Mail\Autoconfig\Exception\AutoconfigException` |
| *(no equivalent)* | `Horde\Mail\Autoconfig\AutoconfigInterface` |
| *(no equivalent)* | `Horde\Mail\Autoconfig\Driver\DriverInterface` |
| *(no equivalent)* | `Horde\Mail\Autoconfig\Dns\DnsResolverInterface` |
| *(no equivalent)* | `Horde\Mail\Autoconfig\Dns\NetDns2Resolver` |
| *(no equivalent)* | `Horde\Mail\Autoconfig\DiscoveryResult` |
| *(no equivalent)* | `Horde\Mail\Autoconfig\TlsMode` |
| *(no equivalent)* | `Horde\Mail\Autoconfig\ServerType` |
| *(no equivalent)* | `Horde\Mail\Autoconfig\ValidationMode` |
| *(no equivalent)* | `Horde\Mail\Autoconfig\Validation\ValidatorInterface` |
| *(no equivalent)* | `Horde\Mail\Autoconfig\Validation\NullValidator` |

## Migration Example

### Before (PSR-0)

```php
$autoconfig = new Horde_Mail_Autoconfig();

$imap = $autoconfig->getMailConfig('user@example.com', [
    'auth' => $password,
    'no_pop3' => true,
]);

if ($imap !== false) {
    echo $imap->host . ':' . $imap->port;
}
```

### After (PSR-4)

```php
use Horde\Mail\Autoconfig\Autoconfig;
use Horde\Mail\Autoconfig\Dns\NetDns2Resolver;
use Horde\Mail\Autoconfig\ServerType;
use Horde\Mail\Autoconfig\ValidationMode;

$dns = new NetDns2Resolver();

$autoconfig = Autoconfig::withBuiltinDrivers($dns, $httpClient, $requestFactory)
    ->withValidation($myValidator, ValidationMode::Next);

$result = $autoconfig->discoverMail('user@example.com', noPop3: true);
$imap   = $result->firstOfType(ServerType::Imap);

if ($imap !== null) {
    echo $imap->host . ':' . $imap->port;
}
```

`withBuiltinDrivers()` wires the three built-in drivers. `withValidation()`
is optional -- omit it to get all discovered servers without validation.

## Validation is now opt-in

The PSR-0 API calls `$server->valid($opts)` internally, opening a live
connection to every candidate using `horde/imap_client` or `horde/smtp`.
The PSR-4 API separates discovery from validation:

- Discovery returns configuration **data**, not verified connections.
- This removes the hard runtime dependency on `horde/imap_client` and
  `horde/smtp` for code that only needs configuration data.

To re-add validate-on-discovery, implement `ValidatorInterface` and chain
`withValidation()` as shown in the migration example above.
