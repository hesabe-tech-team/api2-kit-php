<?php

declare(strict_types=1);

namespace Hesabe\Tests;

use Hesabe\Exception\AuthenticationException;
use Hesabe\Exception\ConfigurationException;
use Hesabe\Http\Response;
use Hesabe\Session\MemoryTokenStore;
use Hesabe\Session\MerchantSession;
use PHPUnit\Framework\TestCase;

final class SessionTest extends TestCase
{
    private float $now = 1_000_000.0;

    public function testLogsInOnceAndReusesTheToken(): void
    {
        $sender = new FakeSender(self::login('AT1', 'RT1'));
        $session = $this->session($sender);
        self::assertSame('AT1', $session->token());
        self::assertSame('AT1', $session->token());
        self::assertCount(1, $sender->calls);
        self::assertStringEndsWith('/api/v1/login', $sender->calls[0]['url']);
        self::assertSame(['username' => 'user', 'password' => 'pass'], json_decode((string) $sender->calls[0]['body'], true));
    }

    public function testRefreshesAMinuteBeforeExpiry(): void
    {
        $sender = new FakeSender(self::login('AT1', 'RT1', 900), self::login('AT2', 'RT2', 900));
        $session = $this->session($sender);
        $session->token();
        $this->now += 841;
        self::assertSame('AT2', $session->token());
        self::assertStringEndsWith('/api/v1/token-refresh', $sender->calls[1]['url']);
        self::assertSame(['refreshToken' => 'RT1'], json_decode((string) $sender->calls[1]['body'], true));
    }

    public function testAStaleRefreshFallsBackToAFullLogin(): void
    {
        $sender = new FakeSender(
            self::login('AT1', 'RT1', 900),
            Support::json(401, ['status' => false, 'message' => 'Unauthenticated.']),
            self::login('AT3', 'RT3', 900),
        );
        $session = $this->session($sender);
        $session->token();
        $this->now += 2000;
        self::assertSame('AT3', $session->token());
        self::assertStringEndsWith('/api/v1/login', $sender->calls[2]['url']);
    }

    public function testAnUnusableExpiryMeansExpired(): void
    {
        $sender = new FakeSender(self::login('AT1', null, 'soon'), self::login('AT2', null, 900));
        $session = $this->session($sender);
        $session->token();
        self::assertSame('AT2', $session->token());
    }

    public function testAMissingPanelLoginIsAConfigurationError(): void
    {
        $sender = new FakeSender(self::login('AT1', 'RT1'));
        $session = new MerchantSession(Support::transport($sender, overrides: ['username' => null, 'password' => null]));
        // Environment variables would otherwise fill the gap on a developer's machine.
        if (getenv('HESABE_MERCHANT_USERNAME') !== false) {
            self::markTestSkipped('HESABE_MERCHANT_USERNAME is set in this environment');
        }
        $this->expectException(ConfigurationException::class);
        $session->token();
    }

    public function testALoginWithoutATokenIsRejected(): void
    {
        $this->expectException(AuthenticationException::class);
        $this->session(new FakeSender(Support::json(200, ['status' => true, 'response' => []])))->token();
    }

    public function testTheTokenSurvivesInTheStoreAcrossSessions(): void
    {
        $store = new MemoryTokenStore();
        $sender = new FakeSender(self::login('AT1', 'RT1'));
        $this->session($sender, $store)->token();
        self::assertSame('AT1', $this->session($sender, $store)->token());
        self::assertCount(1, $sender->calls);
    }

    private function session(FakeSender $sender, ?MemoryTokenStore $store = null): MerchantSession
    {
        return new MerchantSession(Support::transport($sender), $store ?? new MemoryTokenStore(), fn (): float => $this->now);
    }

    private static function login(string $access, ?string $refresh, mixed $expiresIn = 900): Response
    {
        return Support::json(200, [
            'status' => true,
            'response' => [
                'token' => [
                    'token_type' => 'Bearer',
                    'access_token' => $access,
                    'refresh_token' => $refresh,
                    'expires_in' => $expiresIn,
                ],
            ],
        ]);
    }
}
