<?php

declare(strict_types=1);

namespace Hesabe\Tests;

use Hesabe\Http\Response;
use Hesabe\Http\Sender;

/**
 * A scripted sender: hands out queued responses in order and repeats the last one.
 */
final class FakeSender implements Sender
{
    /** @var list<array{method: string, url: string, headers: array<string, string>, body: ?string}> */
    public array $calls = [];

    /** @var list<Response|\Exception> */
    private array $queue;

    public function __construct(Response|\Exception ...$responses)
    {
        $this->queue = array_values($responses);
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): Response
    {
        $this->calls[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];
        $next = count($this->queue) > 1 ? array_shift($this->queue) : $this->queue[0];
        if ($next instanceof \Exception) {
            throw $next;
        }
        return $next;
    }

    /** The decrypted `data` of the call at $index. */
    public function sentPayload(int $index = 0): mixed
    {
        $body = json_decode((string) $this->calls[$index]['body'], true);
        return Support::cipher()->decrypt($body['data']);
    }
}
