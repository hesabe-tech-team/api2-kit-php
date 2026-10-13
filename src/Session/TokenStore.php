<?php

declare(strict_types=1);

namespace Hesabe\Session;

/**
 * Where the Merchant API bearer token lives between calls.
 *
 * PHP forgets everything at the end of a request, and every new panel login ends every
 * earlier token on the account (probed on production, October 2026). A host that makes
 * Merchant API calls from more than one request stores the token somewhere durable, so it
 * reuses one login instead of logging out its own earlier sessions and any other
 * integration on the same account. Tokens are bearer secrets: store them encrypted.
 */
interface TokenStore
{
    /**
     * @return array{accessToken: string, refreshToken: ?string, expiresAt: float}|null
     */
    public function load(): ?array;

    /**
     * @param array{accessToken: string, refreshToken: ?string, expiresAt: float} $token
     */
    public function save(#[\SensitiveParameter] array $token): void;

    public function clear(): void;
}
