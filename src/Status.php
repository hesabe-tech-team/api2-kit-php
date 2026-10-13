<?php

declare(strict_types=1);

namespace Hesabe;

/**
 * Decides whether a transaction record means the money moved.
 */
final class Status
{
    /** Settled, and settled as a split payment (Hesabe's own name; not yet seen live). */
    private const PAID_CODES = [PaymentStatus::SUCCESS, PaymentStatus::MULTIVENDOR_SUCCESS];

    private const PAID_TEXT = ['SUCCESSFUL', 'SUCCESS', 'CAPTURED', 'PAID'];

    /**
     * True only when Hesabe says the payment settled.
     *
     * Not an HTTP status check: the transaction lookup answers 200 for a failed payment as
     * well as a paid one, because 200 means "this transaction exists". Hesabe spells a
     * settled payment differently per surface (the lookup sends the word, merchant reports
     * send the number, redirects and webhooks send the processor's result code), so all
     * three are read, the number first.
     *
     * @param mixed $record A transaction as returned by `Transactions::retrieve()`.
     */
    public static function isSuccessful(mixed $record): bool
    {
        if (!is_array($record)) {
            return false;
        }

        $code = $record['payment_status'] ?? null;
        if (is_bool($code)) {
            return false;
        }
        if (is_int($code)) {
            return in_array($code, self::PAID_CODES, true);
        }
        if (is_string($code) && preg_match('/^\s*\+?([0-9]+)\s*\z/', $code, $match) === 1) {
            return in_array((int) $match[1], self::PAID_CODES, true);
        }

        foreach (['status', 'result_code', 'resultCode'] as $key) {
            $value = $record[$key] ?? null;
            if (is_string($value) && in_array(strtoupper(trim($value)), self::PAID_TEXT, true)) {
                return true;
            }
        }
        return false;
    }
}
