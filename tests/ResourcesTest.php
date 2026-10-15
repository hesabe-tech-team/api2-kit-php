<?php

declare(strict_types=1);

namespace Hesabe\Tests;

use Hesabe\Exception\AuthenticationException;
use Hesabe\Exception\InvalidRequestException;
use Hesabe\Exception\SignatureException;
use Hesabe\Hesabe;
use Hesabe\Http\Response;
use Hesabe\PaymentType;
use Hesabe\RefundMethod;
use PHPUnit\Framework\TestCase;

final class ResourcesTest extends TestCase
{
    public function testCheckoutBuildsTheDocumentedPayload(): void
    {
        $sender = new FakeSender(Support::ok(['data' => 'abc+/='], ['token' => '84221712345']));
        $session = self::client($sender)->checkout->create(
            amount: 1.5,
            orderReferenceNumber: 'ORDER-1',
            responseUrl: 'https://example.com/paid',
            failureUrl: 'https://example.com/failed',
            paymentType: PaymentType::KNET,
            webhookUrl: 'https://example.com/hook',
            variable1: null,
        );

        self::assertSame([
            'amount' => '1.500',
            'currency' => 'KWD',
            'paymentType' => 1,
            'orderReferenceNumber' => 'ORDER-1',
            'responseUrl' => 'https://example.com/paid',
            'failureUrl' => 'https://example.com/failed',
            'version' => '2.0',
            'webhookUrl' => 'https://example.com/hook',
            'merchantCode' => '842217',
        ], $sender->sentPayload());
        self::assertSame('https://gw.test/checkout', $sender->calls[0]['url']);
        self::assertSame('84221712345', $session['token']);
        self::assertSame('https://gw.test/payment?data=abc%2B/%3D', $session['paymentUrl']);
    }

    public function testCheckoutPicksVersion3ForTheFeaturesThatNeedIt(): void
    {
        $sender = new FakeSender(Support::ok(['data' => 'x']));
        $client = self::client($sender);
        $client->checkout->create(1, 'R', 'https://example.com/a', 'https://example.com/b', channel: 'mobile');
        $client->checkout->create(1, 'R', 'https://example.com/a', 'https://example.com/b', embedded: true);
        $client->checkout->create(1, 'R', 'https://example.com/a', 'https://example.com/b', customerId: 0);
        $client->checkout->create(1, 'R', 'https://example.com/a', 'https://example.com/b', version: '2.0', embedded: true);
        self::assertSame('3.0', $sender->sentPayload(0)['version']);
        self::assertSame('3.0', $sender->sentPayload(1)['version']);
        self::assertSame('2.0', $sender->sentPayload(2)['version'], 'customer id 0 is falsy, as in hesabe-python');
        self::assertSame('2.0', $sender->sentPayload(3)['version']);
    }

    public function testTheMerchantCodeCannotBeOverridden(): void
    {
        $sender = new FakeSender(Support::ok(['data' => 'x']));
        self::client($sender)->checkout->create(1, 'R', 'https://example.com/a', 'https://example.com/b', variable1: 'v');
        self::assertSame('842217', $sender->sentPayload()['merchantCode']);
    }

    public function testCheckoutIsNeverReplayed(): void
    {
        $sender = new FakeSender(new Response(502, 'bad gateway'));
        try {
            self::client($sender)->checkout->create(1, 'R', 'https://example.com/a', 'https://example.com/b');
            self::fail('Expected an exception');
        } catch (\Hesabe\Exception\ApiException) {
            self::assertCount(1, $sender->calls);
        }
    }

    public function testLookupByOrderReferenceUsesTheQueryFlag(): void
    {
        $record = ['token' => 'T', 'amount' => '0.200', 'status' => 'SUCCESSFUL'];
        $sender = new FakeSender(Support::json(200, ['status' => true, 'message' => 'Transaction found', 'data' => $record]));
        self::assertSame($record, self::client($sender)->transactions->retrieveByOrderReference('A/B 1'));
        self::assertSame('https://gw.test/api/transaction/A%2FB%201?isOrderReference=1', $sender->calls[0]['url']);
        self::assertSame('ACCESS-CODE', $sender->calls[0]['headers']['accessCode']);
    }

    public function testNotFoundIsATypedInvalidRequest(): void
    {
        $body = ['status' => false, 'message' => 'Transaction not found', 'data' => null];
        $sender = new FakeSender(Support::json(404, $body));
        try {
            self::client($sender, ['maxRetries' => 0])->transactions->retrieve('T');
            self::fail('Expected an InvalidRequestException');
        } catch (InvalidRequestException $exception) {
            self::assertSame(404, $exception->getStatusCode());
        }
    }

    public function testLookupKeysMadeOfDotsAreRefused(): void
    {
        $this->expectException(InvalidRequestException::class);
        self::client(new FakeSender(Support::ok()))->transactions->retrieve('..');
    }

    public function testVerifyRedirectReturnsHesabesCopy(): void
    {
        $redirect = Support::cipher()->encrypt([
            'status' => true,
            'response' => ['paymentToken' => 'T1', 'amount' => '1.5', 'resultCode' => 'CAPTURED'],
        ]);
        $confirmed = ['token' => 'T1', 'amount' => '1.500', 'status' => 'SUCCESSFUL'];
        $sender = new FakeSender(Support::json(200, ['status' => true, 'data' => $confirmed]));
        self::assertSame($confirmed, self::client($sender)->transactions->verifyRedirect($redirect));
        self::assertSame('https://gw.test/api/transaction/T1', $sender->calls[0]['url']);
    }

    public function testVerifyRedirectRejectsADifferentAmount(): void
    {
        $redirect = Support::cipher()->encrypt(['response' => ['paymentToken' => 'T1', 'amount' => '0.100']]);
        $sender = new FakeSender(Support::json(200, ['status' => true, 'data' => ['amount' => '10.000']]));
        $this->expectException(SignatureException::class);
        self::client($sender)->transactions->verifyRedirect($redirect);
    }

    public function testRefundPayloadAndNoReplayOnA401(): void
    {
        $sender = new FakeSender(
            Support::json(200, ['status' => true, 'response' => ['token' => ['access_token' => 'AT']]]),
            Support::json(401, ['status' => false, 'message' => 'Unauthenticated.']),
        );
        try {
            self::client($sender)->refunds->create('T1', '0.250', RefundMethod::PARTIAL);
            self::fail('Expected an AuthenticationException');
        } catch (AuthenticationException) {
            self::assertCount(2, $sender->calls, 'login, then the refund once');
        }
        self::assertSame('https://api.test/api/v1/refund', $sender->calls[1]['url']);
        self::assertSame('Bearer AT', $sender->calls[1]['headers']['Authorization']);
        self::assertSame('ACCESS-CODE', $sender->calls[1]['headers']['accessCode']);
        self::assertSame(
            ['token' => 'T1', 'refundAmount' => '0.250', 'refundMethod' => '2', 'merchantCode' => '842217'],
            $sender->sentPayload(1),
        );
    }

    public function testTheCallAfterARefundsA401SignsInAgain(): void
    {
        $sender = new FakeSender(
            Support::json(200, ['status' => true, 'response' => ['token' => ['access_token' => 'AT1']]]),
            Support::json(401, ['status' => false, 'message' => 'Unauthenticated.']),
            Support::json(200, ['status' => true, 'response' => ['token' => ['access_token' => 'AT2']]]),
            Support::ok(['id' => 8, 'status' => 0]),
        );
        $client = self::client($sender);
        try {
            $client->refunds->create('T1', '0.250', RefundMethod::PARTIAL);
            self::fail('Expected an AuthenticationException');
        } catch (AuthenticationException) {
        }
        self::assertSame(['id' => 8, 'status' => 0], $client->refunds->create('T1', '0.250', RefundMethod::PARTIAL));
        self::assertSame('https://api.test/api/v1/login', $sender->calls[2]['url']);
        self::assertSame('Bearer AT2', $sender->calls[3]['headers']['Authorization']);
    }

    public function testAReadIsReplayedOnceWithAFreshLogin(): void
    {
        $sender = new FakeSender(
            Support::json(200, ['status' => true, 'response' => ['token' => ['access_token' => 'AT1']]]),
            Support::json(401, ['status' => false, 'message' => 'Unauthenticated.']),
            Support::json(200, ['status' => true, 'response' => ['token' => ['access_token' => 'AT2']]]),
            Support::ok(['id' => 7, 'status' => 0]),
        );
        self::assertSame(['id' => 7, 'status' => 0], self::client($sender)->refunds->retrieve(7));
        self::assertSame('Bearer AT2', $sender->calls[3]['headers']['Authorization']);
    }

    public function testUnknownRefundMethodsAreRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        self::client(new FakeSender(Support::ok()))->refunds->create('T1', '1', '0');
    }

    public function testAWebhookIsCheckedAgainstTheLookup(): void
    {
        $confirmed = ['token' => 'T1', 'amount' => '0.200', 'status' => 'SUCCESSFUL'];
        $sender = new FakeSender(Support::json(200, ['status' => true, 'data' => $confirmed]));
        $body = '{"token":"T1","amount":"0.2","status":"SUCCESSFUL","status_code":1}';
        self::assertSame($confirmed, self::client($sender)->webhooks->verify($body));
    }

    public function testAForgedWebhookIsRejected(): void
    {
        $sender = new FakeSender(Support::json(200, ['status' => true, 'data' => ['amount' => '0.200', 'status' => 'FAILED']]));
        $this->expectException(SignatureException::class);
        self::client($sender)->webhooks->verify(['token' => 'T1', 'amount' => '0.200', 'status' => 'SUCCESSFUL']);
    }

    public function testAWebhookWithoutATokenIsRejected(): void
    {
        $this->expectException(SignatureException::class);
        self::client(new FakeSender(Support::ok()))->webhooks->parse('[]');
    }

    /**
     * @param array<string, string|int|float|null> $overrides
     */
    private static function client(FakeSender $sender, array $overrides = []): Hesabe
    {
        return new Hesabe(Support::config($overrides), $sender);
    }
}
