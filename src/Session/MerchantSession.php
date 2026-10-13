<?php

declare(strict_types=1);

namespace Hesabe\Session;

use Hesabe\Exception\AuthenticationException;
use Hesabe\Exception\ConfigurationException;
use Hesabe\Exception\HesabeException;
use Hesabe\Http\Transport;

/**
 * The bearer token in front of the Merchant API (refunds, invoices, reports).
 *
 * It logs in with the Merchant Panel username and password on first use, refreshes a minute
 * ahead of expiry, and falls back to a full login when the refresh token is stale too. A
 * refresh rotates both tokens and ends the current access token at once (probed October
 * 2026), so a refreshed token is saved before it is used.
 *
 * @internal
 */
final class MerchantSession
{
    private const REFRESH_MARGIN_SECONDS = 60;

    /** Hesabe's documented lifetime, used when a response leaves `expires_in` out. */
    private const DEFAULT_EXPIRES_IN_SECONDS = 900;

    private readonly \Closure $clock;

    /**
     * @param (\Closure(): float)|null $clock Seconds since the epoch; tests pass a fixed clock.
     */
    public function __construct(
        private readonly Transport $transport,
        private readonly TokenStore $store = new MemoryTokenStore(),
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    public function token(): string
    {
        $cached = $this->store->load();
        if ($cached !== null && ($this->clock)() < $cached['expiresAt'] - self::REFRESH_MARGIN_SECONDS) {
            return $cached['accessToken'];
        }
        return $this->acquire($cached['refreshToken'] ?? null);
    }

    public function invalidate(): void
    {
        $this->store->clear();
    }

    private function acquire(?string $refreshToken): string
    {
        if ($refreshToken !== null) {
            try {
                return $this->exchange('api/v1/token-refresh', ['refreshToken' => $refreshToken]);
            } catch (HesabeException) {
                $this->store->clear();
            }
        }

        $config = $this->transport->config;
        if ($config->username === null || $config->password === null) {
            throw new ConfigurationException(
                'Merchant API access needs the Merchant Panel login. Set username and password '
                . '(HESABE_MERCHANT_USERNAME, HESABE_MERCHANT_PASSWORD).'
            );
        }
        return $this->exchange('api/v1/login', ['username' => $config->username, 'password' => $config->password]);
    }

    /**
     * @param array<string, string> $payload
     */
    private function exchange(string $path, #[\SensitiveParameter] array $payload): string
    {
        $result = $this->transport->request(
            method: 'POST',
            path: $path,
            baseUrl: $this->transport->config->merchantApiUrl,
            payload: $payload,
            encrypted: false,
            idempotent: false,
        );
        $token = is_array($result) && is_array($result['token'] ?? null) ? $result['token'] : [];
        $accessToken = $token['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new AuthenticationException('Hesabe did not return an access token');
        }

        $refreshToken = $token['refresh_token'] ?? null;
        $this->store->save([
            'accessToken' => $accessToken,
            'refreshToken' => is_string($refreshToken) && $refreshToken !== '' ? $refreshToken : null,
            'expiresAt' => ($this->clock)() + self::expiresIn($token['expires_in'] ?? null),
        ]);
        return $accessToken;
    }

    /**
     * Absent means the documented 15 minutes. Anything unusable (zero, negative, not a
     * number) means expired, so a token the server may have declared dead is never trusted.
     */
    private static function expiresIn(mixed $value): float
    {
        if ($value === null) {
            return self::DEFAULT_EXPIRES_IN_SECONDS;
        }
        if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
            return 0.0;
        }
        $seconds = (float) $value;
        return $seconds >= 0 && $seconds < 1e9 ? $seconds : 0.0;
    }
}
