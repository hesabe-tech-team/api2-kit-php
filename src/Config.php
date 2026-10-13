<?php

declare(strict_types=1);

namespace Hesabe;

use Hesabe\Exception\ConfigurationException;

/**
 * Credentials and endpoints for one merchant account.
 *
 * Every option falls back to its `HESABE_*` environment variable. The secrets are hidden
 * from var_dump() and print_r(), because this object hangs off every resource and one
 * debug dump would otherwise print the AES key and the panel password.
 */
final class Config
{
    public const ENVIRONMENTS = [
        'sandbox' => [
            'gateway' => 'https://sandbox.hesabe.com',
            'merchantApi' => 'https://merchantapisandbox.hesabe.com',
        ],
        // merchantapi.hesabe.com is absent from the docs; verified live in August 2026.
        'production' => [
            'gateway' => 'https://api.hesabe.com',
            'merchantApi' => 'https://merchantapi.hesabe.com',
        ],
    ];

    private const OPTIONS = [
        'merchantCode' => 'HESABE_MERCHANT_CODE',
        'accessCode' => 'HESABE_ACCESS_CODE',
        'secretKey' => 'HESABE_SECRET_KEY',
        'ivKey' => 'HESABE_IV_KEY',
        'environment' => 'HESABE_ENVIRONMENT',
        'username' => 'HESABE_MERCHANT_USERNAME',
        'password' => 'HESABE_MERCHANT_PASSWORD',
        'gatewayUrl' => 'HESABE_GATEWAY_URL',
        'merchantApiUrl' => 'HESABE_MERCHANT_API_URL',
        'timeout' => 'HESABE_TIMEOUT',
        'maxRetries' => 'HESABE_MAX_RETRIES',
    ];

    private function __construct(
        public readonly string $merchantCode,
        public readonly string $accessCode,
        public readonly string $secretKey,
        public readonly string $ivKey,
        public readonly string $environment,
        public readonly string $gatewayUrl,
        public readonly string $merchantApiUrl,
        public readonly float $timeout,
        public readonly int $maxRetries,
        public readonly ?string $username,
        public readonly ?string $password,
    ) {
    }

    /**
     * @param array<string, string|int|float|null> $options Keys as in `OPTIONS`; null or
     *     absent falls back to the environment variable.
     * @throws ConfigurationException for an unknown option, a missing credential or an
     *     unrecognised environment.
     */
    public static function resolve(#[\SensitiveParameter] array $options = []): self
    {
        $unknown = array_diff(array_keys($options), array_keys(self::OPTIONS));
        if ($unknown !== []) {
            throw new ConfigurationException('Unknown option: ' . implode(', ', $unknown));
        }
        $value = static function (string $option) use ($options): ?string {
            $given = $options[$option] ?? null;
            if ($given !== null && $given !== '') {
                return (string) $given;
            }
            $fromEnvironment = getenv(self::OPTIONS[$option]);
            return is_string($fromEnvironment) && $fromEnvironment !== '' ? $fromEnvironment : null;
        };

        $environment = self::environment($value('environment'));
        $defaults = self::ENVIRONMENTS[$environment];

        return new self(
            merchantCode: self::required($value('merchantCode'), 'merchantCode'),
            accessCode: self::required($value('accessCode'), 'accessCode'),
            secretKey: self::required($value('secretKey'), 'secretKey'),
            ivKey: self::required($value('ivKey'), 'ivKey'),
            environment: $environment,
            gatewayUrl: rtrim($value('gatewayUrl') ?? $defaults['gateway'], '/'),
            merchantApiUrl: rtrim($value('merchantApiUrl') ?? $defaults['merchantApi'], '/'),
            timeout: self::positiveNumber($value('timeout'), 30.0),
            maxRetries: self::count($value('maxRetries'), 2),
            username: $value('username'),
            password: $value('password'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'merchantCode' => $this->merchantCode,
            'environment' => $this->environment,
            'gatewayUrl' => $this->gatewayUrl,
            'merchantApiUrl' => $this->merchantApiUrl,
            'username' => $this->username,
            'accessCode' => '[hidden]',
            'secretKey' => '[hidden]',
            'ivKey' => '[hidden]',
            'password' => $this->password === null ? null : '[hidden]',
        ];
    }

    /**
     * Anything unrecognised is rejected rather than treated as sandbox: a typo in a
     * production deploy would otherwise send live traffic to the sandbox, where payments
     * succeed and no money moves.
     */
    private static function environment(?string $value): string
    {
        if ($value === null) {
            return 'sandbox';
        }
        if (isset(self::ENVIRONMENTS[$value])) {
            return $value;
        }
        throw new ConfigurationException(
            "Unrecognised environment \"{$value}\". It must be exactly 'sandbox' or 'production'."
        );
    }

    private static function required(?string $value, string $option): string
    {
        if ($value === null) {
            $variable = self::OPTIONS[$option];
            throw new ConfigurationException("Missing {$option}. Pass it as an option or set {$variable}.");
        }
        return $value;
    }

    /** Anything unusable (text, zero, negative, infinite) falls back. */
    private static function positiveNumber(?string $value, float $fallback): float
    {
        if ($value === null || !is_numeric($value)) {
            return $fallback;
        }
        $number = (float) $value;
        return $number > 0 && $number < 1e9 ? $number : $fallback;
    }

    /**
     * Retry counts are whole and non-negative; a negative one would skip the request loop
     * and fail without ever reaching Hesabe.
     */
    private static function count(?string $value, int $fallback): int
    {
        if ($value === null || !is_numeric($value)) {
            return $fallback;
        }
        $number = (float) $value;
        return $number >= 0 && $number < 100 ? (int) $number : $fallback;
    }
}
