<?php

declare(strict_types=1);

namespace Hesabe\Tests;

use Hesabe\Exception\ApiException;
use Hesabe\Exception\AuthenticationException;
use Hesabe\Exception\CardException;
use Hesabe\Exception\ConnectionException;
use Hesabe\Exception\HesabeException;
use Hesabe\Exception\InvalidRequestException;
use Hesabe\Exception\RateLimitException;
use Hesabe\Http\Response;
use Hesabe\Http\Transport;
use PHPUnit\Framework\TestCase;

final class TransportTest extends TestCase
{
    private const HTML = '<html><body>502 Bad Gateway</body></html>';

    public function testBareHexBody(): void
    {
        $body = Support::cipher()->encrypt(['status' => true, 'response' => ['ok' => 1]]);
        self::assertSame(['ok' => 1], $this->envelope(new Response(200, $body))['response']);
    }

    public function testJsonWrappedHexString(): void
    {
        $body = json_encode(Support::cipher()->encrypt(['status' => true, 'response' => ['ok' => 2]]));
        self::assertSame(['ok' => 2], $this->envelope(new Response(200, (string) $body))['response']);
    }

    public function testNestedResponseHex(): void
    {
        $inner = Support::cipher()->encrypt(['status' => true, 'response' => ['ok' => 3]]);
        $body = json_encode(['response' => $inner]);
        self::assertSame(['ok' => 3], $this->envelope(new Response(200, (string) $body))['response']);
    }

    public function testPlainJsonEnvelope(): void
    {
        $body = json_encode(['status' => true, 'response' => ['ok' => 4]]);
        self::assertSame(['ok' => 4], $this->envelope(new Response(200, (string) $body))['response']);
    }

    public function testHtmlAtA200IsAnApiError(): void
    {
        $this->expectException(ApiException::class);
        $this->envelope(new Response(200, self::HTML));
    }

    public function testAnEncryptedListIsAnApiError(): void
    {
        $this->expectException(ApiException::class);
        $this->envelope(new Response(200, Support::cipher()->encrypt([1, 2, 3])));
    }

    public function testUnparseable5xxRetriesIdempotentCalls(): void
    {
        $sender = new FakeSender(new Response(502, self::HTML), Support::ok(['v' => 1]));
        self::assertSame(['v' => 1], self::get(Support::transport($sender)));
        self::assertCount(2, $sender->calls);
    }

    public function testUnparseable5xxFailsFastForNonIdempotentCalls(): void
    {
        $sender = new FakeSender(new Response(502, self::HTML));
        try {
            self::post(Support::transport($sender));
            self::fail('Expected an ApiException');
        } catch (ApiException) {
            self::assertCount(1, $sender->calls);
        }
    }

    public function test429RetriesEvenNonIdempotentCalls(): void
    {
        $rejected = Support::json(429, ['status' => false, 'message' => 'Too many requests']);
        $sender = new FakeSender($rejected, Support::ok(['v' => 2]));
        self::assertSame(['v' => 2], self::post(Support::transport($sender)));
        self::assertCount(2, $sender->calls);
    }

    public function test429ExhaustsIntoARateLimitError(): void
    {
        $sender = new FakeSender(Support::json(429, ['status' => false, 'message' => 'Too many requests']));
        $slept = [];
        try {
            self::get(Support::transport($sender, $slept, ['maxRetries' => 1]));
            self::fail('Expected a RateLimitException');
        } catch (RateLimitException) {
            self::assertCount(2, $sender->calls);
        }
    }

    public function testRetryAfterSecondsWinOverBackoff(): void
    {
        $rejected = Support::json(429, ['status' => false, 'message' => 'slow down'], ['Retry-After' => '7']);
        $slept = [];
        self::assertSame(['v' => 3], self::get(Support::transport(new FakeSender($rejected, Support::ok(['v' => 3])), $slept)));
        self::assertSame([7.0], $slept);
    }

    public function testRetryAfterIsClampedTo30Seconds(): void
    {
        $rejected = Support::json(503, ['status' => false, 'message' => 'maintenance'], ['Retry-After' => '600']);
        $slept = [];
        self::get(Support::transport(new FakeSender($rejected, Support::ok(['v' => 4])), $slept));
        self::assertSame([30.0], $slept);
    }

    public function testMalformedRetryAfterFallsBackToBackoff(): void
    {
        $rejected = Support::json(429, ['status' => false, 'message' => 'slow down'], ['Retry-After' => 'soon']);
        $slept = [];
        self::get(Support::transport(new FakeSender($rejected, Support::ok(['v' => 5])), $slept));
        self::assertCount(1, $slept);
        self::assertLessThan(5.0, $slept[0]);
    }

    public function testConnectionErrorsRetryIdempotentCalls(): void
    {
        $sender = new FakeSender(new \RuntimeException('boom'), Support::ok(['v' => 6]));
        self::assertSame(['v' => 6], self::get(Support::transport($sender)));
        self::assertCount(2, $sender->calls);
    }

    public function testConnectionErrorsFailFastForNonIdempotentCalls(): void
    {
        $sender = new FakeSender(new \RuntimeException('boom'));
        try {
            self::post(Support::transport($sender));
            self::fail('Expected a ConnectionException');
        } catch (ConnectionException) {
            self::assertCount(1, $sender->calls);
        }
    }

    public function test401IsAnAuthenticationError(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->failWith(Support::json(401, ['status' => false, 'message' => 'Invalid Signature.']));
    }

    public function testADeclineMessageIsACardError(): void
    {
        $this->expectException(CardException::class);
        $this->failWith(Support::json(400, ['status' => false, 'message' => 'Insufficient funds']));
    }

    public function testFieldErrorsAreExtracted(): void
    {
        $body = [
            'status' => false,
            'message' => 'Invalid Request Data',
            'data' => ['amount' => ['The amount field is required.']],
        ];
        try {
            $this->failWith(Support::json(422, $body));
            self::fail('Expected an InvalidRequestException');
        } catch (InvalidRequestException $exception) {
            self::assertSame(['amount' => ['The amount field is required.']], $exception->getFieldErrors());
        }
    }

    public function testAReadableBodyAtAnErrorStatusIsNotSuccess(): void
    {
        $this->expectException(AuthenticationException::class);
        self::get(Support::transport(new FakeSender(Support::json(403, ['message' => 'forbidden']))));
    }

    public function testARedirectIsRefusedRatherThanReturned(): void
    {
        $sender = new FakeSender(Support::json(301, ['url' => 'https://elsewhere.test']));
        try {
            self::get(Support::transport($sender));
            self::fail('Expected a HesabeException');
        } catch (HesabeException) {
            self::assertCount(1, $sender->calls);
        }
    }

    public function testANonStringMessageDoesNotCrashTheErrorPath(): void
    {
        $body = Support::cipher()->encrypt(['status' => false, 'message' => ['en' => 'Declined'], 'code' => 506]);
        try {
            self::get(Support::transport(new FakeSender(new Response(400, $body))));
            self::fail('Expected a HesabeException');
        } catch (HesabeException $exception) {
            self::assertStringContainsString('Declined', $exception->getMessage());
            self::assertSame(506, $exception->getHesabeCode());
        }
    }

    public function testAGetPayloadIsEncryptedIntoTheDataQueryParameter(): void
    {
        $sender = new FakeSender(Support::ok());
        Support::transport($sender)->request('GET', 'api/thing', 'https://gw.test', payload: ['merchantCode' => '842217']);
        parse_str((string) parse_url($sender->calls[0]['url'], PHP_URL_QUERY), $query);
        self::assertSame(['merchantCode' => '842217'], Support::cipher()->decrypt((string) $query['data']));
        self::assertNull($sender->calls[0]['body']);
    }

    public function testAPostPayloadIsEncryptedIntoTheDataBodyField(): void
    {
        $sender = new FakeSender(Support::ok());
        self::post(Support::transport($sender), ['amount' => '1.000']);
        self::assertSame(['amount' => '1.000'], $sender->sentPayload());
    }

    public function testTheAccessCodeGoesOnlyOnEncryptedCalls(): void
    {
        $sender = new FakeSender(Support::json(200, ['status' => true, 'response' => []]));
        Support::transport($sender)->request(
            'POST',
            'login',
            'https://api.test',
            payload: ['username' => 'u'],
            encrypted: false,
            idempotent: false,
        );
        self::assertArrayNotHasKey('accessCode', $sender->calls[0]['headers']);

        $encrypted = new FakeSender(Support::ok());
        Support::transport($encrypted)->request('GET', 'api/thing', 'https://gw.test', payload: ['x' => 1]);
        self::assertSame('ACCESS-CODE', $encrypted->calls[0]['headers']['accessCode']);
    }

    public function testConnectionErrorsDoNotCarryTheRequest(): void
    {
        $sender = new FakeSender(new \RuntimeException('connection reset'));
        try {
            Support::transport($sender)->request(
                'POST',
                'api/v1/login',
                'https://api.test',
                payload: ['password' => 'hunter2'],
                encrypted: false,
                idempotent: false,
            );
            self::fail('Expected a ConnectionException');
        } catch (ConnectionException $exception) {
            self::assertNull($exception->getRaw());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('hunter2', $exception->getMessage());
        }
    }

    public function testUnencryptedErrorBodiesAreNotAttached(): void
    {
        $body = ['status' => false, 'message' => 'nope', 'token' => ['access_token' => 'AT']];
        try {
            Support::transport(new FakeSender(Support::json(401, $body)))->request(
                'POST',
                'api/v1/login',
                'https://api.test',
                payload: ['username' => 'u'],
                encrypted: false,
                idempotent: false,
            );
            self::fail('Expected an AuthenticationException');
        } catch (AuthenticationException $exception) {
            self::assertNull($exception->getRaw());
        }
    }

    public function testOnlyPlainSecondsAndHttpDatesAreHonoured(): void
    {
        foreach (['inf', 'nan', '1_000', '0x10', '', ' ', '-3', 'abc', '1e3'] as $header) {
            self::assertNull(Transport::retryAfterSeconds(['retry-after' => $header]), $header);
        }
        self::assertSame(5.0, Transport::retryAfterSeconds(['retry-after' => '5']));
        self::assertSame(0.0, Transport::retryAfterSeconds(['retry-after' => '0']));
        self::assertSame(30.0, Transport::retryAfterSeconds(['retry-after' => '900']));

        $soon = gmdate('D, d M Y H:i:s', time() + 10) . ' GMT';
        $seconds = Transport::retryAfterSeconds(['retry-after' => $soon]);
        self::assertNotNull($seconds);
        self::assertGreaterThan(5.0, $seconds);
        self::assertLessThanOrEqual(10.0, $seconds);
    }

    public function testResponseHeaderNamesAreCaseInsensitive(): void
    {
        self::assertSame(['retry-after' => '7'], (new Response(429, '', ['Retry-After' => '7']))->headers);
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(Response $response): array
    {
        return Support::transport(new FakeSender($response))->envelope('GET', 'x', 'https://gw.test');
    }

    private function failWith(Response $response): void
    {
        $slept = [];
        self::get(Support::transport(new FakeSender($response), $slept, ['maxRetries' => 0]));
    }

    private static function get(Transport $transport): mixed
    {
        return $transport->request('GET', 'api/thing', 'https://gw.test');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function post(Transport $transport, array $payload = ['a' => 1]): mixed
    {
        return $transport->request('POST', 'api/thing', 'https://gw.test', payload: $payload, idempotent: false);
    }
}
