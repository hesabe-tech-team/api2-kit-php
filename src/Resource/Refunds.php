<?php

declare(strict_types=1);

namespace Hesabe\Resource;

use Hesabe\Amount;
use Hesabe\RefundMethod;

/**
 * Refund requests, on the Merchant API.
 *
 * The gateway's documented `/api/refund` answers 404 to every shape; this endpoint works
 * with the Merchant Panel login, a bearer token and the accessCode together (probed on the
 * sandbox and production, October 2026). A request waits for Hesabe to approve it and the
 * minimum is 0.250 KWD on both; on the sandbox a transaction holds one pending request at a
 * time.
 */
final class Refunds extends Resource
{
    /**
     * Submits a refund request against a paid transaction. Never retried: a lost answer may
     * mean Hesabe recorded it, and a second request could refund twice. Check `list()` first.
     *
     * @param string $method `RefundMethod::FULL` or `RefundMethod::PARTIAL`.
     * @return array<string, mixed>
     */
    public function create(string $token, string|int|float $amount, string $method = RefundMethod::PARTIAL): array
    {
        if (!in_array($method, [RefundMethod::FULL, RefundMethod::PARTIAL], true)) {
            throw new \InvalidArgumentException("Unknown refund method: {$method}");
        }
        return self::record($this->merchant(
            'POST',
            'api/v1/refund',
            payload: $this->withMerchantCode([
                'token' => $token,
                'refundAmount' => Amount::format($amount),
                'refundMethod' => $method,
            ]),
            idempotent: false,
        ));
    }

    /**
     * Returns a page of refund requests: `data`, `pagination` and `stats`.
     *
     * @return array<string, mixed>
     */
    public function list(?string $fromDate = null, ?string $toDate = null, int $page = 1, ?string $search = null): array
    {
        $result = self::record($this->merchant(
            'GET',
            'api/v1/refund/',
            payload: $this->withMerchantCode(),
            query: ['page' => $page, 'fromDate' => $fromDate, 'toDate' => $toDate, 'search' => $search],
        ));
        $body = self::record($result['refund_request_list'] ?? null);
        $body['stats'] = $result['stats'] ?? null;
        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieve(int $refundId): array
    {
        return self::record($this->merchant('GET', "api/v1/refund/{$refundId}", payload: $this->withMerchantCode()));
    }

    /**
     * Withdraws a request that has not been approved yet. Hesabe answers success for an
     * unknown or already cancelled id as well (probed October 2026).
     */
    public function cancel(int $refundId): void
    {
        $this->merchant('DELETE', "api/v1/refund/{$refundId}", payload: $this->withMerchantCode(), idempotent: false);
    }

    /**
     * @return array<string, mixed>
     */
    private static function record(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
