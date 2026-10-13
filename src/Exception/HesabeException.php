<?php

declare(strict_types=1);

namespace Hesabe\Exception;

/**
 * Base of every error the SDK raises on purpose, so a caller can catch one type at the edge
 * of a request.
 *
 * The HTTP status and Hesabe's own `code` travel separately because Hesabe sets them
 * independently: an HTTP 200 can carry code 424, and an HTTP 422 can carry code 506.
 */
class HesabeException extends \RuntimeException
{
    /**
     * @param array<string, list<mixed>> $fieldErrors Laravel-style validation messages by field.
     * @param mixed $raw The decrypted response, when it is safe to keep. Never set for
     *     unencrypted bodies, which are the login calls and hold tokens in clear text.
     */
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
        private readonly ?int $hesabeCode = null,
        private readonly array $fieldErrors = [],
        private readonly mixed $raw = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $hesabeCode ?? 0, $previous);
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getHesabeCode(): ?int
    {
        return $this->hesabeCode;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }

    public function getRaw(): mixed
    {
        return $this->raw;
    }
}
