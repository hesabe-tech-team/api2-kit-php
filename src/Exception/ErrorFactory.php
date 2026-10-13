<?php

declare(strict_types=1);

namespace Hesabe\Exception;

/**
 * Turns an error answer from Hesabe into the matching exception type.
 *
 * @internal
 */
final class ErrorFactory
{
    private const DECLINE_PATTERNS = [
        'insufficient funds',
        'do not honour',
        'do not honor',
        'declined',
        'not eligible for merchant-initiated charge',
        'expired card',
        'stolen',
        'lost card',
        'invalid card',
    ];

    /**
     * @param array<string, list<mixed>> $fieldErrors
     */
    public static function fromResponse(
        mixed $message,
        ?int $statusCode = null,
        ?int $hesabeCode = null,
        array $fieldErrors = [],
        mixed $raw = null,
    ): HesabeException {
        // Hesabe usually sends a string, but a localised {"en": ..., "ar": ...} object shows
        // up too, and an error path must never crash on one.
        if (!is_string($message)) {
            $message = json_encode($message, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                ?: 'Hesabe request failed';
        }
        $normalized = strtolower($message);

        if ($statusCode === 401 || $statusCode === 403 || $hesabeCode === 501) {
            return new AuthenticationException($message, $statusCode, $hesabeCode, $fieldErrors, $raw);
        }
        if ($statusCode === 429) {
            return new RateLimitException($message, $statusCode, $hesabeCode, $fieldErrors, $raw);
        }
        foreach (self::DECLINE_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return new CardException($message, $statusCode, $hesabeCode, $fieldErrors, $raw);
            }
        }
        if ($statusCode !== null && $statusCode >= 500) {
            return new ApiException($message, $statusCode, $hesabeCode, $fieldErrors, $raw);
        }
        return new InvalidRequestException($message, $statusCode, $hesabeCode, $fieldErrors, $raw);
    }
}
