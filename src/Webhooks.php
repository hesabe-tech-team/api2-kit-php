<?php

declare(strict_types=1);

namespace Hesabe;

use Hesabe\Exception\SignatureException;
use Hesabe\Resource\Transactions;

/**
 * Reads Hesabe's webhook POST.
 *
 * The webhook is plain JSON with no signature and no shared secret (probed on the sandbox,
 * October 2026): anyone who learns the URL can forge one. It arrives one to three seconds
 * before the customer's browser returns, fires for KNET failures too, and does not fire at
 * all for card declines.
 */
final class Webhooks
{
    public function __construct(private readonly Transactions $transactions)
    {
    }

    /**
     * Decodes a webhook body without trusting it.
     *
     * @param string|array<string, mixed> $body
     * @return array<string, mixed>
     * @throws SignatureException when the body is not JSON or names no transaction.
     */
    public function parse(string|array $body): array
    {
        if (is_string($body)) {
            try {
                $body = json_decode($body, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            } catch (\JsonException) {
                throw new SignatureException('Webhook body is not valid JSON');
            }
        }
        $token = is_array($body) ? ($body['token'] ?? null) : null;
        if (!is_array($body) || !is_string($token) || $token === '') {
            throw new SignatureException('Webhook payload has no transaction token');
        }
        /** @var array<string, mixed> $body */
        return $body;
    }

    /**
     * Re-reads the transaction a webhook names and returns Hesabe's copy, after checking the
     * webhook agrees with it. Use this, not `parse()`, before releasing goods.
     *
     * @param string|array<string, mixed> $body
     * @return array<string, mixed>
     * @throws SignatureException when the webhook disagrees with Hesabe.
     */
    public function verify(string|array $body): array
    {
        $claimed = $this->parse($body);
        /** @var non-empty-string $token parse() refuses a body without one. */
        $token = $claimed['token'];
        $confirmed = $this->transactions->retrieve($token);

        if (
            ($confirmed['status'] ?? null) !== ($claimed['status'] ?? null)
            || Transactions::comparableAmount($confirmed['amount'] ?? null)
                !== Transactions::comparableAmount($claimed['amount'] ?? null)
        ) {
            throw new SignatureException("Webhook does not match transaction {$token}");
        }
        return $confirmed;
    }
}
