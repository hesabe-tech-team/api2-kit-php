<?php

declare(strict_types=1);

namespace Hesabe\Resource;

use Hesabe\Exception\AuthenticationException;
use Hesabe\Http\Transport;
use Hesabe\Session\MerchantSession;

/**
 * Shared plumbing for the endpoint groups: which host, which credentials, and the merchant
 * code every payload carries.
 */
abstract class Resource
{
    public function __construct(
        protected readonly Transport $transport,
        protected readonly MerchantSession $session,
    ) {
    }

    /**
     * Adds the merchant code last, so a stray caller key cannot point the call at another
     * merchant's account. Null values are dropped, not sent as nulls.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function withMerchantCode(array $payload = []): array
    {
        $merged = array_filter($payload, static fn (mixed $value): bool => $value !== null);
        $merged['merchantCode'] = $this->transport->config->merchantCode;
        return $merged;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string|int|float|null> $query
     */
    protected function gateway(
        string $method,
        string $path,
        ?array $payload = null,
        array $query = [],
        ?bool $idempotent = null,
    ): mixed {
        return $this->transport->request(
            method: $method,
            path: $path,
            baseUrl: $this->transport->config->gatewayUrl,
            payload: $payload,
            query: $query,
            idempotent: $idempotent,
        );
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    protected function gatewayEnvelope(string $method, string $path, ?array $payload, bool $idempotent): array
    {
        return $this->transport->envelope(
            method: $method,
            path: $path,
            baseUrl: $this->transport->config->gatewayUrl,
            payload: $payload,
            idempotent: $idempotent,
        );
    }

    /**
     * Calls the Merchant API, which needs the bearer token and the accessCode together.
     *
     * @param array<string, mixed>|null $payload
     * @param array<string, string|int|float|null> $query
     */
    protected function merchant(
        string $method,
        string $path,
        ?array $payload = null,
        array $query = [],
        ?bool $idempotent = null,
    ): mixed {
        $send = fn (string $bearer): mixed => $this->transport->request(
            method: $method,
            path: $path,
            baseUrl: $this->transport->config->merchantApiUrl,
            payload: $payload,
            query: $query,
            bearerToken: $bearer,
            idempotent: $idempotent,
        );

        // Taken outside the retry so a rejected panel login surfaces as itself instead of
        // provoking a second login.
        $bearer = $this->session->token();
        try {
            return $send($bearer);
        } catch (AuthenticationException $exception) {
            // A 401 can arrive after Hesabe processed the call, and there are no idempotency
            // keys: replaying a refund would move money twice. Only replayable calls get a
            // second attempt with a fresh token.
            if (!($idempotent ?? $method === 'GET')) {
                throw $exception;
            }
            $this->session->invalidate();
            return $send($this->session->token());
        }
    }
}
