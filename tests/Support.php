<?php

declare(strict_types=1);

namespace Hesabe\Tests;

use Hesabe\Cipher;
use Hesabe\Config;
use Hesabe\Http\Response;
use Hesabe\Http\Transport;

/**
 * Shared fixtures for the unit tests. The keys are Hesabe's published sandbox keys, which
 * are public documentation.
 */
final class Support
{
    public const SECRET_KEY = 'PkW64zMe5NVdrlPVNnjo2Jy9nOb7v1Xg';
    public const IV_KEY = '5NVdrlPVNnjo2Jy9';

    public static function cipher(): Cipher
    {
        return new Cipher(self::SECRET_KEY, self::IV_KEY);
    }

    /**
     * @return array<mixed>
     */
    public static function fixture(string $name): array
    {
        $contents = file_get_contents(__DIR__ . '/../fixtures/' . $name);
        return json_decode((string) $contents, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, string|int|float|null> $overrides
     */
    public static function config(array $overrides = []): Config
    {
        return Config::resolve($overrides + [
            'merchantCode' => '842217',
            'accessCode' => 'ACCESS-CODE',
            'secretKey' => self::SECRET_KEY,
            'ivKey' => self::IV_KEY,
            'environment' => 'sandbox',
            'gatewayUrl' => 'https://gw.test',
            'merchantApiUrl' => 'https://api.test',
            'timeout' => 5,
            'maxRetries' => 2,
            'username' => 'user',
            'password' => 'pass',
        ]);
    }

    /**
     * @param list<float> $slept Receives every delay the transport waited.
     * @param array<string, string|int|float|null> $overrides
     */
    public static function transport(FakeSender $sender, array &$slept = [], array $overrides = []): Transport
    {
        return new Transport(
            self::config($overrides),
            $sender,
            static function (float $seconds) use (&$slept): void {
                $slept[] = $seconds;
            },
        );
    }

    /** A 200 whose body is the encrypted success envelope. */
    public static function ok(mixed $payload = [], array $extra = []): Response
    {
        return new Response(200, self::cipher()->encrypt(['status' => true, 'response' => $payload] + $extra));
    }

    /**
     * @param array<string, string> $headers
     */
    public static function json(int $status, mixed $body, array $headers = []): Response
    {
        return new Response($status, (string) json_encode($body), $headers);
    }
}
