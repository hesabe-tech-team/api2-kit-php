<?php

declare(strict_types=1);

namespace Hesabe\Http;

use Hesabe\Cipher;
use Hesabe\Config;
use Hesabe\Exception\ApiException;
use Hesabe\Exception\ConnectionException;
use Hesabe\Exception\ErrorFactory;
use Hesabe\Hesabe;

/**
 * Builds, sends and reads every call: encryption, retries, and the shapes Hesabe answers in.
 *
 * Retries follow one rule: Hesabe has no idempotency keys, so a call that may have been
 * processed is never replayed. GETs replay; a 429 replays (Hesabe rejected it before doing
 * anything); every other non-idempotent failure surfaces at once. Replaying an ambiguous
 * refund POST could move money twice.
 *
 * @internal
 */
final class Transport
{
    private const RETRYABLE_STATUS = [408, 429, 500, 502, 503, 504];
    private const RETRY_AFTER_STATUS = [429, 503];
    private const RETRY_AFTER_MAX_SECONDS = 30.0;
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    public readonly Cipher $cipher;
    private readonly Sender $sender;
    private readonly \Closure $sleep;

    /**
     * @param (\Closure(float): void)|null $sleep Waits between attempts; tests pass a recorder.
     */
    public function __construct(public readonly Config $config, ?Sender $sender = null, ?\Closure $sleep = null)
    {
        $this->cipher = new Cipher($config->secretKey, $config->ivKey);
        $this->sender = $sender ?? new CurlSender();
        $this->sleep = $sleep ?? static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
    }

    /**
     * Sends a call and returns the body Hesabe nests under `response` (or `data`).
     *
     * @param array<string, mixed>|null $payload
     * @param array<string, string|int|float|null> $query
     */
    public function request(
        string $method,
        string $path,
        string $baseUrl,
        ?array $payload = null,
        array $query = [],
        bool $encrypted = true,
        ?string $bearerToken = null,
        ?bool $idempotent = null,
    ): mixed {
        $envelope = $this->envelope($method, $path, $baseUrl, $payload, $query, $encrypted, $bearerToken, $idempotent);
        return $envelope['response'] ?? $envelope['data'] ?? null;
    }

    /**
     * Sends a call and returns Hesabe's whole decrypted envelope.
     *
     * @param array<string, mixed>|null $payload
     * @param array<string, string|int|float|null> $query
     * @return array<string, mixed>
     */
    public function envelope(
        string $method,
        string $path,
        string $baseUrl,
        ?array $payload = null,
        array $query = [],
        bool $encrypted = true,
        ?string $bearerToken = null,
        ?bool $idempotent = null,
    ): array {
        $replayable = $idempotent ?? $method === 'GET';
        $url = $this->buildUrl($baseUrl, $path, $payload, $query, $encrypted, $method);
        [$body, $headers] = $this->buildBody($payload, $encrypted, $method, $bearerToken);

        $lastError = null;
        $delay = null;
        for ($attempt = 0; $attempt <= $this->config->maxRetries; $attempt++) {
            if ($attempt > 0) {
                ($this->sleep)($delay ?? self::backoff($attempt - 1));
                $delay = null;
            }

            try {
                $response = $this->sender->send($method, $url, $headers, $body, $this->config->timeout);
            } catch (\Exception $exception) {
                // Only the message is kept: the request, and on login its body, holds the panel
                // password.
                $lastError = new ConnectionException(
                    "Could not reach Hesabe at {$url}: {$exception->getMessage()}"
                );
                if ($replayable) {
                    continue;
                }
                throw $lastError;
            }

            $status = $response->status;
            $canRetry = ($status === 429 || ($replayable && in_array($status, self::RETRYABLE_STATUS, true)))
                && $attempt < $this->config->maxRetries;

            try {
                $envelope = $this->parse($response->body, $encrypted, $status);
            } catch (ApiException $exception) {
                if ($canRetry) {
                    $lastError = $exception;
                    $delay = self::retryDelay($status, $response->headers, $attempt);
                    continue;
                }
                throw $exception;
            }

            // Login and refresh run unencrypted, so their bodies hold the bearer and refresh
            // tokens in clear text. Never attach one to an error.
            $safeRaw = $encrypted ? $envelope : null;

            if (($envelope['status'] ?? null) === false) {
                $error = ErrorFactory::fromResponse(
                    $envelope['message'] ?? 'Hesabe request failed',
                    $status,
                    self::code($envelope['code'] ?? null),
                    self::fieldErrors($envelope['data'] ?? null),
                    $safeRaw,
                );
                if ($canRetry) {
                    $lastError = $error;
                    $delay = self::retryDelay($status, $response->headers, $attempt);
                    continue;
                }
                throw $error;
            }

            if (in_array($status, self::RETRYABLE_STATUS, true)) {
                $lastError = new ApiException("Hesabe returned HTTP {$status}", $status, null, [], $safeRaw);
                if ($canRetry) {
                    $delay = self::retryDelay($status, $response->headers, $attempt);
                    continue;
                }
                throw $lastError;
            }

            if ($status >= 300) {
                // A readable body without status:false is still an error when the status says
                // so: a proxy 403 or a redirect must not read as success because the JSON parsed.
                throw ErrorFactory::fromResponse(
                    $envelope['message'] ?? "Hesabe returned HTTP {$status}",
                    $status,
                    self::code($envelope['code'] ?? null),
                    [],
                    $safeRaw,
                );
            }

            return $envelope;
        }

        throw $lastError ?? new ApiException('Hesabe request failed after retries');
    }

    /**
     * Reads Retry-After as plain seconds or an HTTP date, and nothing else, capped at 30.
     *
     * @param array<string, string> $headers Lower-cased names.
     */
    public static function retryAfterSeconds(array $headers): ?float
    {
        $value = trim($headers['retry-after'] ?? '');
        if ($value === '') {
            return null;
        }
        if (preg_match('/^[0-9]+(?:\.[0-9]+)?\z/', $value) === 1) {
            // Not is_numeric(): it also takes "1e3", " 5" and hexadecimal-looking forms that the
            // sibling SDKs refuse.
            $seconds = (float) $value;
        } else {
            // HTTP's IMF-fixdate, spelled out: the RFC7231 constant is deprecated in PHP 8.5.
            $when = \DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $value, new \DateTimeZone('UTC'));
            if ($when === false) {
                return null;
            }
            $seconds = (float) $when->getTimestamp() - microtime(true);
        }
        return max(0.0, min($seconds, self::RETRY_AFTER_MAX_SECONDS));
    }

    private static function backoff(int $attempt): float
    {
        return min(2 ** $attempt * 0.25, 4.0) + random_int(0, 200) / 1000;
    }

    /**
     * @param array<string, string> $headers
     */
    private static function retryDelay(int $status, array $headers, int $attempt): float
    {
        if (in_array($status, self::RETRY_AFTER_STATUS, true)) {
            $retryAfter = self::retryAfterSeconds($headers);
            if ($retryAfter !== null) {
                return $retryAfter;
            }
        }
        return self::backoff($attempt);
    }

    private static function code(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        return is_string($value) && preg_match('/^[0-9]+\z/', $value) === 1 ? (int) $value : null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    private static function fieldErrors(mixed $value): array
    {
        if (!is_array($value) || array_is_list($value)) {
            return [];
        }
        $errors = [];
        foreach ($value as $field => $messages) {
            if (is_array($messages) && array_is_list($messages)) {
                $errors[(string) $field] = $messages;
            }
        }
        return $errors;
    }

    /**
     * @param array<string, mixed>|null $payload
     * @param array<string, string|int|float|null> $query
     */
    private function buildUrl(
        string $baseUrl,
        string $path,
        ?array $payload,
        array $query,
        bool $encrypted,
        string $method,
    ): string {
        $params = [];
        if ($encrypted && $method === 'GET' && $payload !== null) {
            $params['data'] = $this->cipher->encrypt($payload);
        }
        foreach ($query as $key => $value) {
            if ($value !== null) {
                $params[$key] = (string) $value;
            }
        }
        $url = $baseUrl . '/' . ltrim($path, '/');
        return $params === [] ? $url : $url . '?' . http_build_query($params);
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array{0: ?string, 1: array<string, string>}
     */
    private function buildBody(?array $payload, bool $encrypted, string $method, ?string $bearerToken): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'User-Agent' => 'hesabe-php/' . Hesabe::VERSION,
        ];
        if ($encrypted) {
            $headers['accessCode'] = $this->config->accessCode;
        }
        if ($bearerToken !== null) {
            $headers['Authorization'] = "Bearer {$bearerToken}";
        }

        if ($method === 'GET' || $payload === null) {
            return [null, $headers];
        }
        $body = $encrypted ? ['data' => $this->cipher->encrypt($payload)] : $payload;
        return [json_encode($body, self::JSON_FLAGS), $headers];
    }

    /**
     * Hesabe answers with a bare hex string on success but drops to plain JSON for auth
     * failures and the login endpoints, so the body is sniffed rather than assumed from the
     * status code.
     *
     * @return array<string, mixed>
     */
    private function parse(string $raw, bool $encrypted, int $status): array
    {
        $trimmed = trim($raw);
        if ($encrypted && Cipher::isHex($trimmed)) {
            return self::requireObject($this->cipher->decrypt($trimmed), $status);
        }

        try {
            $parsed = json_decode($trimmed, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            throw new ApiException(
                "Unreadable response from Hesabe (HTTP {$status})",
                $status,
                null,
                [],
                $encrypted ? substr($trimmed, 0, 500) : null,
            );
        }

        if (is_string($parsed) && Cipher::isHex($parsed)) {
            return self::requireObject($this->cipher->decrypt($parsed), $status);
        }
        if ($parsed instanceof \stdClass) {
            $nested = $parsed->response ?? null;
            if (is_string($nested) && Cipher::isHex($nested)) {
                return self::requireObject($this->cipher->decrypt($nested), $status);
            }
            // Decoded twice so `{}` stays distinguishable from `[]` above, then as arrays here.
            /** @var array<string, mixed> $object */
            $object = (array) json_decode($trimmed, true, 512, JSON_BIGINT_AS_STRING);
            return $object;
        }

        throw new ApiException(
            "Unexpected response from Hesabe (HTTP {$status})",
            $status,
            null,
            [],
            $encrypted ? substr($trimmed, 0, 500) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function requireObject(mixed $value, int $status): array
    {
        if (!is_array($value) || ($value !== [] && array_is_list($value))) {
            throw new ApiException("Unexpected response from Hesabe (HTTP {$status})", $status, null, [], $value);
        }
        return $value;
    }
}
