<?php

declare(strict_types=1);

namespace Hesabe\Tests;

use Hesabe\Config;
use Hesabe\Exception\ConfigurationException;
use Hesabe\Hesabe;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    public function testDefaultsToTheSandbox(): void
    {
        $config = Support::config(['environment' => null, 'gatewayUrl' => null, 'merchantApiUrl' => null]);
        if (getenv('HESABE_ENVIRONMENT') !== false) {
            self::markTestSkipped('HESABE_ENVIRONMENT is set in this environment');
        }
        self::assertSame('sandbox', $config->environment);
        self::assertSame('https://sandbox.hesabe.com', $config->gatewayUrl);
        self::assertSame('https://merchantapisandbox.hesabe.com', $config->merchantApiUrl);
    }

    public function testProductionHosts(): void
    {
        $config = Support::config(['environment' => 'production', 'gatewayUrl' => null, 'merchantApiUrl' => null]);
        self::assertSame('https://api.hesabe.com', $config->gatewayUrl);
        self::assertSame('https://merchantapi.hesabe.com', $config->merchantApiUrl);
    }

    public function testAnUnknownEnvironmentIsRefusedRatherThanTreatedAsSandbox(): void
    {
        $this->expectException(ConfigurationException::class);
        Support::config(['environment' => 'Production']);
    }

    public function testAnUnknownOptionIsRefused(): void
    {
        $this->expectException(ConfigurationException::class);
        Config::resolve(['merchant_code' => '842217']);
    }

    public function testAMissingCredentialNamesItsVariable(): void
    {
        if (getenv('HESABE_ACCESS_CODE') !== false) {
            self::markTestSkipped('HESABE_ACCESS_CODE is set in this environment');
        }
        $this->expectExceptionMessage('HESABE_ACCESS_CODE');
        Support::config(['accessCode' => null]);
    }

    public function testUnusableNumbersFallBack(): void
    {
        $config = Support::config(['timeout' => 'abc', 'maxRetries' => -1]);
        self::assertSame(30.0, $config->timeout);
        self::assertSame(2, $config->maxRetries);
    }

    public function testSecretsStayOutOfDebugOutput(): void
    {
        $dump = print_r(Support::config(), true) . print_r(new Hesabe(Support::config()), true);
        foreach ([Support::SECRET_KEY, Support::IV_KEY, 'ACCESS-CODE', 'pass]'] as $secret) {
            self::assertStringNotContainsString($secret, $dump);
        }
    }
}
