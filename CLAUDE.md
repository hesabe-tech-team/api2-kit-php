# hesabe-php

Hand-written PHP SDK for the Hesabe payment gateway (Kuwait), ported from
[hesabe-python](https://github.com/ruptyx/hesabe-python), which stays the reference.
It started on 2026-10-13 for the PrestaShop module, so it covers checkout, transaction
lookup, refunds and webhooks first.

## Testing

All PHP runs in Docker on the Windows machine (no local PHP):

```bash
MSYS_NO_PATHCONV=1 docker run --rm -v "C:/projects/sdk/php:/app" -w /app composer:2 composer install
MSYS_NO_PATHCONV=1 docker run --rm -v "C:/projects/sdk/php:/app" -w /app php:8.1-cli php vendor/bin/phpunit
MSYS_NO_PATHCONV=1 docker run --rm -v "C:/projects/sdk/php:/app" -w /app php:8.1-cli php -d memory_limit=1G vendor/bin/phpstan analyse
MSYS_NO_PATHCONV=1 docker run --rm -v "C:/projects/sdk/php:/app" -w /app php:8.1-cli php tests/smoke.php
```

- The unit suite is hermetic: a scripted `Http\Sender` (`tests/FakeSender.php`), no network.
- The smoke suite hits the live sandbox with Hesabe's published test account, which is
  public documentation and shared by everyone who reads those docs.
- CI runs PHP 8.1 to 8.5 (`.github/workflows/ci.yml`).

## Key Locations

- Transport (retries, Retry-After, envelope sniffing, no redirects): `src/Http/Transport.php`
- curl sender (TLS verified, whole-call timeout): `src/Http/CurlSender.php`
- Crypto (AES-256-CBC, 32-byte padding, hex, via openssl): `src/Cipher.php`
- Amount formatting (decimal strings, half-up, no bcmath): `src/Amount.php`
- Bearer-token session and pluggable `TokenStore`: `src/Session/`
- Endpoint groups: `src/Resource/`
- Cross-SDK parity fixtures: `fixtures/` (byte-identical copies of hesabe-python's)

## Cardinal Rules

The rules of hesabe-python apply here unchanged. Read its CLAUDE.md before touching
crypto, amounts, retries or secrets. In short:

1. **Probed behaviour beats the docs.** Never change code to match developer.hesabe.com
   without proving it against the sandbox.
2. **Behaviourally identical to hesabe-python and hesabe-node.** Crypto and amount changes
   land in all three and keep `fixtures/cipher-vectors.json` and `amount-vectors.json`
   passing. Those files are copied from hesabe-python, never edited here.
3. **No dependencies beyond ext-curl, ext-json, ext-openssl.** Shops cannot be assumed to
   have bcmath, sodium or a PSR-18 client. Never hand-roll AES; openssl does it.
4. **All money goes through `Amount::format()`.** Never float arithmetic on amounts.
5. **Never trust customer-transported payloads.** Redirects and webhooks are triggers for a
   lookup; `Status::isSuccessful()` of the lookup decides.
6. **No replay of an ambiguous POST.** GETs and 429s retry; a 401 on a Merchant API POST
   surfaces instead of re-logging in and replaying, because a refund may already be done.
7. **The crypto is HesabeCrypt-compatible, not textbook.** 32-byte pad-length padding,
   OPENSSL_ZERO_PADDING, lowercase hex, and the zero-padded / exact-block fallback on
   decrypt.

## Conventions

- PHP 8.1+, `declare(strict_types=1)`, PSR-4 under `Hesabe\`, four-space indent, lines up to
  about 110 characters.
- Responses are plain associative arrays with Hesabe's own keys; SDK-made values
  (`paymentUrl`) are camelCase.
- Errors: the typed `Hesabe\Exception\*` hierarchy. Nothing attacker-reachable (redirect
  data, webhook bodies, gateway responses) escapes as a bare `JsonException` or `TypeError`.
- Secrets never enter an exception message, `raw`, a log line or a debug dump:
  `__debugInfo()` hides them and `#[\SensitiveParameter]` keeps them out of stack traces.
- Comments explain why or document a function, never what the code obviously does.
- LF line endings (`.gitattributes`); `core.autocrlf` is on globally on the Windows machine.

## Known Loose Ends

- 2026-10-13: the sandbox no longer returns `webviewUrl` for `channel: "mobile"` sessions.
  hesabe-python's smoke check fails the same way, so this is Hesabe's change, not ours.
- Not ported: invoices, open and bulk invoices, saved cards, customers, subscriptions,
  authorize and capture, reports, profile, Smart POS.
