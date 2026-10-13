<?php

declare(strict_types=1);

namespace Hesabe;

use Hesabe\Exception\ConfigurationException;
use Hesabe\Exception\SignatureException;

/**
 * AES-256-CBC as Hesabe speaks it: padded to 32-byte blocks (pad byte = pad length),
 * cipher-level padding off, lowercase hex.
 *
 * This is Hesabe's reference-kit scheme, not PKCS#7 over 16 bytes. The gateway accepts
 * 16-byte PKCS#7 requests but answers padded to 32, so a textbook unpad fails on real
 * responses (probed October 2026). Ciphertext is byte-identical to hesabe-python and
 * hesabe-node through `fixtures/cipher-vectors.json`.
 *
 * Decrypting proves possession of the key, not integrity: CBC carries no MAC. Anything that
 * travelled through a customer's browser is re-read from the API before it decides anything.
 */
final class Cipher
{
    private const METHOD = 'aes-256-cbc';
    private const BLOCK = 32;
    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR;

    private readonly string $key;
    private readonly string $iv;

    public function __construct(#[\SensitiveParameter] string $secretKey, #[\SensitiveParameter] string $ivKey)
    {
        $this->key = self::keyBytes($secretKey, 32, 'secretKey');
        $this->iv = self::keyBytes($ivKey, 16, 'ivKey');
    }

    /**
     * Keeps the key out of var_dump(), print_r() and debug tooling.
     *
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['key' => '[hidden]', 'iv' => '[hidden]'];
    }

    public static function isHex(string $value): bool
    {
        return $value !== '' && strlen($value) % 2 === 0 && preg_match('/^[0-9a-fA-F]+\z/', $value) === 1;
    }

    /**
     * @param array<mixed> $payload
     * @throws \InvalidArgumentException when the payload holds a value JSON cannot carry.
     */
    public function encrypt(array $payload): string
    {
        try {
            // Unicode stays \u-escaped, as Python's json.dumps does by default, so non-ASCII
            // payloads also encrypt identically in both SDKs.
            $body = json_encode($payload, self::JSON_FLAGS);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Payload cannot be encoded as JSON', 0, $exception);
        }
        $ciphertext = openssl_encrypt(
            self::pad($body),
            self::METHOD,
            $this->key,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            $this->iv,
        );
        if ($ciphertext === false) {
            throw new SignatureException('OpenSSL could not encrypt the payload');
        }
        return bin2hex($ciphertext);
    }

    /**
     * @throws SignatureException when the text is not a payload this key produced.
     */
    public function decrypt(string $ciphertext): mixed
    {
        $normalized = trim($ciphertext);
        if (!self::isHex($normalized)) {
            throw new SignatureException('Expected a hex-encoded payload');
        }
        $raw = (string) hex2bin($normalized);
        $decrypted = strlen($raw) % 16 === 0
            ? openssl_decrypt($raw, self::METHOD, $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $this->iv)
            : false;
        if ($decrypted === false) {
            throw new SignatureException('Ciphertext is not a whole number of blocks');
        }

        // The merchant API sometimes zero-pads a response, or sends an exactly block-sized
        // plaintext with no padding at all (probed on production, August 2026). Accepted
        // only when the remainder parses as strict JSON below.
        $unpadded = self::unpad($decrypted) ?? rtrim($decrypted, "\0");
        try {
            return json_decode($unpadded, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            throw new SignatureException('Decrypted payload is not valid JSON');
        }
    }

    private static function pad(string $plaintext): string
    {
        // Byte length, not character count: padding by characters corrupts non-ASCII text.
        $length = self::BLOCK - (strlen($plaintext) % self::BLOCK);
        return $plaintext . str_repeat(chr($length), $length);
    }

    private static function unpad(string $padded): ?string
    {
        $length = $padded === '' ? 0 : ord($padded[-1]);
        // Every pad byte carries the pad length, so checking all of them rejects more
        // tampering than trusting the last byte alone.
        if (
            $length === 0
            || $length > self::BLOCK
            || $length > strlen($padded)
            || substr($padded, -$length) !== str_repeat(chr($length), $length)
        ) {
            return null;
        }
        return substr($padded, 0, -$length);
    }

    private static function keyBytes(#[\SensitiveParameter] string $value, int $size, string $name): string
    {
        if (strlen($value) !== $size) {
            throw new ConfigurationException("{$name} must be exactly {$size} bytes, received " . strlen($value));
        }
        return $value;
    }
}
