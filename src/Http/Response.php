<?php

declare(strict_types=1);

namespace Hesabe\Http;

/**
 * One HTTP answer, whatever its status. Header names are lower-cased on the way in so a
 * custom sender cannot break the Retry-After lookup with its spelling.
 */
final class Response
{
    /** @var array<string, string> */
    public readonly array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        array $headers = [],
    ) {
        $this->headers = array_change_key_case($headers, CASE_LOWER);
    }
}
