# hesabe-php

Hesabe Payment Gateway SDK for PHP: checkout, transaction lookup, refunds and webhooks.
Also available for [Python](https://github.com/ruptyx/hesabe-python) and
[Node.js](https://github.com/ruptyx/hesabe-node); the three produce byte-identical
ciphertext and amounts, checked by the shared fixtures in `fixtures/`.

Requires PHP 8.1 or later with `curl`, `json` and `openssl`, which every PHP shop platform
already needs. No other dependencies.

Not on Packagist yet. Install from GitHub, pinning the release tag:

```json
{
    "repositories": [{"type": "vcs", "url": "https://github.com/ruptyx/hesabe-php"}],
    "require": {"ruptyx/hesabe": "^1.0"}
}
```

## Setup

Options left out fall back to environment variables. The environment defaults to the
sandbox, and anything other than `sandbox` or `production` is refused.

```bash
HESABE_ENVIRONMENT=sandbox          # or production
HESABE_MERCHANT_CODE=...
HESABE_ACCESS_CODE=...
HESABE_SECRET_KEY=...               # 32 characters
HESABE_IV_KEY=...                   # 16 characters
HESABE_MERCHANT_USERNAME=...        # refunds only: the Merchant Panel login
HESABE_MERCHANT_PASSWORD=...
HESABE_TIMEOUT=30                   # optional, seconds, whole call
HESABE_MAX_RETRIES=2                # optional
```

```php
use Hesabe\Hesabe;

$hesabe = new Hesabe();                       // from the environment
$hesabe = new Hesabe([                        // or in code
    'merchantCode' => '...',
    'accessCode' => '...',
    'secretKey' => '...',
    'ivKey' => '...',
    'environment' => 'production',
]);
```

## Take a payment

```php
use Hesabe\PaymentType;

$session = $hesabe->checkout->create(
    amount: '12.500',
    orderReferenceNumber: 'ORDER-1001',
    responseUrl: 'https://shop.example.com/hesabe/return',
    failureUrl: 'https://shop.example.com/hesabe/return',
    paymentType: PaymentType::KNET,           // or HOSTED_CHECKOUT for Hesabe's own page
    webhookUrl: 'https://shop.example.com/hesabe/webhook',
);

header('Location: ' . $session['paymentUrl']);
```

`amount` takes a string, an int or a float and always leaves as exactly three decimals,
rounded half-up on decimal strings, never floats. `version` is chosen for you.

## Decide whether it was paid

The outcome comes from Hesabe's transaction lookup, never from the device:

- The redirect's `data` is encrypted but carries no MAC, and it travels through the
  customer's browser.
- The webhook is unsigned plain JSON.
- Card declines and failed 3-D Secure send no redirect and no webhook at all (probed on the
  sandbox, October 2026).

Treat every redirect and webhook as a reason to look the payment up, and keep looking up
payments that never came back.

```php
use Hesabe\Exception\InvalidRequestException;
use Hesabe\Status;

try {
    $transaction = $hesabe->transactions->retrieveByOrderReference('ORDER-1001');
} catch (InvalidRequestException $exception) {
    if ($exception->getStatusCode() !== 404) {
        throw $exception;
    }
    $transaction = null;                      // nothing at Hesabe yet: unpaid or declined
}

if ($transaction !== null && Status::isSuccessful($transaction)) {
    // Compare $transaction['amount'] with the order before fulfilling.
}
```

`verifyRedirect()` and `webhooks->verify()` do the same for a redirect payload or a
webhook body: they decrypt or decode it, re-read the transaction, and refuse a mismatch.
The Apple Pay redirect carries no payment token, so look those up by order reference.

## Refunds

Refunds go through the Merchant API, which needs the Merchant Panel username and password
as well as the gateway keys. The gateway's documented `/api/refund` answers 404 to every
shape we tried.

```php
use Hesabe\RefundMethod;

$refund = $hesabe->refunds->create(token: $transaction['token'], amount: '5.000', method: RefundMethod::PARTIAL);
```

What we observed in October 2026:

- A refund is a request that waits for Hesabe's approval, on the sandbox and on production.
- The minimum is 0.250 KWD on both. A smaller amount answers 422 "The refund amount must be
  at least 0.250."
- A transaction holds one pending request at a time (sandbox).
- A refund is never retried automatically, because a lost answer may mean Hesabe already
  recorded it. Check `refunds->list()` before trying again.

Every new panel login ends every earlier Merchant API token on the account, including
those held by other integrations. To reuse one login across PHP requests, pass a durable
`Session\TokenStore`, and keep the tokens encrypted:

```php
$hesabe = new Hesabe($options, tokenStore: new MyEncryptedTokenStore());
```

## Errors

Every error the SDK raises extends `Hesabe\Exception\HesabeException`, which carries the
HTTP status, Hesabe's own code and any field errors:

| Exception | Meaning |
|---|---|
| `ConfigurationException` | A missing or malformed option, raised before any call |
| `AuthenticationException` | A wrong access code, merchant code, panel login or token |
| `InvalidRequestException` | Hesabe refused the request; a 404 lookup means "nothing there yet" |
| `CardException` | The issuer declined |
| `RateLimitException` | Too many requests (the gateway announces 60 a minute) |
| `ApiException` | A 5xx or an unreadable answer |
| `ConnectionException` | No answer at all |
| `SignatureException` | A payload did not decrypt, or disagreed with Hesabe |

`Amount::format()` and argument checks throw `\InvalidArgumentException`.

GETs are retried with backoff. A 429 is retried for any call, because Hesabe rejected it
before doing anything. Other failed POSTs are never replayed, because Hesabe has no
idempotency keys.

## Not ported yet

hesabe-python also covers invoices, saved cards, subscriptions, authorize and capture,
reports and Smart POS. This SDK ports what a shop needs first; the rest follows the same
patterns.

## Development

```bash
composer install
composer test          # unit suite, hermetic
composer analyse       # PHPStan, level 9
composer smoke         # live sandbox, Hesabe's published test account
```

MIT licensed.
