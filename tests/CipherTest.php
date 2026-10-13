<?php

declare(strict_types=1);

namespace Hesabe\Tests;

use Hesabe\Cipher;
use Hesabe\Exception\ConfigurationException;
use Hesabe\Exception\SignatureException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CipherTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function vectors(): iterable
    {
        foreach (Support::fixture('cipher-vectors.json') as $vector) {
            yield $vector['name'] => [$vector];
        }
    }

    /**
     * The three SDKs must produce byte-identical ciphertext for these payloads.
     *
     * @param array<string, mixed> $vector
     */
    #[DataProvider('vectors')]
    public function testInteropVector(array $vector): void
    {
        $cipher = new Cipher($vector['key'], $vector['iv']);
        // decryptOnly vectors are server response shapes (zero-padded or unpadded): the
        // cipher reads them but never produces them.
        if (!($vector['decryptOnly'] ?? false)) {
            self::assertSame($vector['ciphertextHex'], $cipher->encrypt($vector['payload']));
        }
        self::assertSame($vector['payload'], $cipher->decrypt($vector['ciphertextHex']));
    }

    public function testAcceptsZeroPaddedJson(): void
    {
        $body = '{"a":1}';
        self::assertSame(['a' => 1], Support::cipher()->decrypt(self::raw($body . str_repeat("\0", 32 - strlen($body)))));
    }

    public function testAcceptsAnUnpaddedExactBlock(): void
    {
        $body = '{"m":"' . str_repeat('x', 24) . '"}';
        self::assertSame(32, strlen($body));
        self::assertSame(['m' => str_repeat('x', 24)], Support::cipher()->decrypt(self::raw($body)));
    }

    public function testZeroStrippedRemainderMustStillBeJson(): void
    {
        $this->expectException(SignatureException::class);
        Support::cipher()->decrypt(self::raw('{"a":1' . str_repeat("\0", 26)));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function hostileBlocks(): iterable
    {
        yield 'zero padding byte' => [str_repeat('A', 31) . "\0"];
        yield 'padding longer than a block' => [str_repeat('A', 31) . chr(33)];
        yield 'valid padding, not JSON' => [str_repeat("\xff", 31) . "\x01"];
        yield 'padding bytes that disagree' => ['{"a":1}' . str_repeat("\x09", 8) . str_repeat("\xaa", 16) . "\x09"];
    }

    #[DataProvider('hostileBlocks')]
    public function testRejectsHostileBlocks(string $block): void
    {
        $this->expectException(SignatureException::class);
        Support::cipher()->decrypt(self::raw($block));
    }

    /**
     * Reachable from a redirect URL: `?data=abcd` must fail typed, not as an OpenSSL warning.
     *
     * @return iterable<string, array{string}>
     */
    public static function malformedHex(): iterable
    {
        foreach (['abcd', '00', 'deadbeef', '', 'zz', 'abc'] as $bad) {
            yield "'{$bad}'" => [$bad];
        }
    }

    #[DataProvider('malformedHex')]
    public function testRejectsMalformedInput(string $bad): void
    {
        $this->expectException(SignatureException::class);
        Support::cipher()->decrypt($bad);
    }

    public function testRejectsWrongKeyLengths(): void
    {
        $this->expectException(ConfigurationException::class);
        new Cipher('short', Support::IV_KEY);
    }

    public function testRejectsWrongIvLength(): void
    {
        $this->expectException(ConfigurationException::class);
        new Cipher(Support::SECRET_KEY, 'short');
    }

    public function testIsHex(): void
    {
        self::assertTrue(Cipher::isHex('00ff'));
        self::assertTrue(Cipher::isHex('ABCDEF12'));
        foreach (['', '0', '0g', 'xyz!', "00\n"] as $bad) {
            self::assertFalse(Cipher::isHex($bad), $bad);
        }
    }

    public function testNonAsciiRoundTrip(): void
    {
        $payload = ['name' => 'أحمد المنصوري', 'amount' => '9.900'];
        self::assertSame($payload, Support::cipher()->decrypt(Support::cipher()->encrypt($payload)));
    }

    public function testRoundTripAtEveryPaddingBoundary(): void
    {
        for ($filler = 0; $filler <= 64; $filler++) {
            $payload = ['m' => str_repeat('x', $filler)];
            self::assertSame($payload, Support::cipher()->decrypt(Support::cipher()->encrypt($payload)));
        }
    }

    public function testKeysStayOutOfDebugOutput(): void
    {
        self::assertStringNotContainsString(Support::SECRET_KEY, print_r(Support::cipher(), true));
    }

    /** Encrypts a raw block without Hesabe padding, to craft server-shaped or hostile input. */
    private static function raw(string $block): string
    {
        $ciphertext = openssl_encrypt(
            $block,
            'aes-256-cbc',
            Support::SECRET_KEY,
            OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
            Support::IV_KEY,
        );
        return bin2hex((string) $ciphertext);
    }
}
