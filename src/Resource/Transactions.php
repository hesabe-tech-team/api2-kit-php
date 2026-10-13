<?php

declare(strict_types=1);

namespace Hesabe\Resource;

use Hesabe\Amount;
use Hesabe\Exception\InvalidRequestException;
use Hesabe\Exception\SignatureException;

/**
 * The transaction lookup, the only source of truth about a payment.
 *
 * A 404 "Transaction not found" (an `InvalidRequestException` with status 404) means Hesabe
 * holds nothing under that key: unknown, unpaid, or declined by a card scheme. It is the
 * normal state of a checkout in flight, not a failure.
 */
final class Transactions extends Resource
{
    /**
     * Looks a transaction up by the payment token Hesabe issued at checkout.
     *
     * @return array<string, mixed>
     */
    public function retrieve(string $paymentToken): array
    {
        return self::record($this->gateway('GET', 'api/transaction/' . self::segment($paymentToken, 'paymentToken')));
    }

    /**
     * Looks a transaction up by the `orderReferenceNumber` sent at checkout.
     *
     * The flag goes in the query: the docs put it in the body of the GET, and a form body
     * answers 404 (probed October 2026).
     *
     * @return array<string, mixed>
     */
    public function retrieveByOrderReference(string $orderReferenceNumber): array
    {
        return self::record($this->gateway(
            'GET',
            'api/transaction/' . self::segment($orderReferenceNumber, 'orderReferenceNumber'),
            query: ['isOrderReference' => 1],
        ));
    }

    /**
     * Decrypts the `data` query parameter Hesabe appends when it sends the customer back.
     *
     * The payload travels through the customer's browser, and decrypting it proves key
     * possession, not integrity. Use `verifyRedirect()` or a lookup before fulfilling.
     *
     * @return array<string, mixed>
     * @throws SignatureException when the text was not encrypted with this account's key.
     */
    public function parseRedirect(string $data): array
    {
        $envelope = $this->transport->cipher->decrypt($data);
        $body = is_array($envelope) ? ($envelope['response'] ?? null) : null;
        if (is_array($body) && is_array($body['data'] ?? null)) {
            $body = $body['data'];
        }
        $result = $body ?? $envelope;
        if (!is_array($result)) {
            throw new SignatureException('Redirect payload is not an object');
        }
        /** @var array<string, mixed> $result */
        return $result;
    }

    /**
     * Decrypts a redirect payload, re-reads the transaction from the API, and returns that
     * authoritative copy. Gate fulfilment on `Status::isSuccessful()` of the result.
     *
     * The Apple Pay redirect carries no `paymentToken`, so this throws for it; look the
     * payment up by order reference instead.
     *
     * @return array<string, mixed>
     * @throws SignatureException when the payload has no token or disagrees with Hesabe.
     */
    public function verifyRedirect(string $data): array
    {
        $claimed = $this->parseRedirect($data);
        $token = $claimed['paymentToken'] ?? null;
        if (!is_scalar($token) || (string) $token === '') {
            throw new SignatureException('Redirect payload has no payment token');
        }

        $confirmed = $this->retrieve((string) $token);
        if (self::comparableAmount($confirmed['amount'] ?? null) !== self::comparableAmount($claimed['amount'] ?? null)) {
            throw new SignatureException("Redirect does not match transaction {$token}");
        }
        return $confirmed;
    }

    /**
     * Hesabe reports amounts as strings in some payloads and numbers in others.
     *
     * @internal
     */
    public static function comparableAmount(mixed $value): mixed
    {
        if (is_string($value) || is_int($value) || is_float($value)) {
            try {
                return Amount::format($value);
            } catch (\InvalidArgumentException) {
                return $value;
            }
        }
        return $value;
    }

    /**
     * Dots survive rawurlencode(), and URL normalisation collapses them, so ".." would
     * address a different endpoint. Neither is a real key.
     */
    private static function segment(string $value, string $name): string
    {
        if ($value === '' || trim($value, '.') === '') {
            throw new InvalidRequestException("{$name} must be a non-empty identifier");
        }
        return rawurlencode($value);
    }

    /**
     * @return array<string, mixed>
     */
    private static function record(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
