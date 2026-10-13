<?php

declare(strict_types=1);

namespace Hesabe\Http;

/**
 * Moves one request over the wire.
 *
 * Implementations never throw on an HTTP status; only a transport failure (DNS, TLS,
 * timeout) may throw. Tests swap in a scripted sender, and a host application can route
 * the SDK through its own HTTP stack.
 */
interface Sender
{
    /**
     * @param array<string, string> $headers
     * @throws \Exception when no response arrived.
     */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): Response;
}
