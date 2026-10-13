<?php

declare(strict_types=1);

namespace Hesabe\Session;

/**
 * Keeps the token for the life of the PHP process. The default, and right for scripts and
 * workers; a web host that calls the Merchant API often passes a durable store instead.
 */
final class MemoryTokenStore implements TokenStore
{
    /** @var array{accessToken: string, refreshToken: ?string, expiresAt: float}|null */
    private ?array $token = null;

    public function load(): ?array
    {
        return $this->token;
    }

    public function save(#[\SensitiveParameter] array $token): void
    {
        $this->token = $token;
    }

    public function clear(): void
    {
        $this->token = null;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['token' => $this->token === null ? 'none' : '[hidden]'];
    }
}
