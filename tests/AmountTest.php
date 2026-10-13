<?php

declare(strict_types=1);

namespace Hesabe\Tests;

use Hesabe\Amount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AmountTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function vectors(): iterable
    {
        foreach (Support::fixture('amount-vectors.json') as $index => $vector) {
            yield $index . ': ' . var_export($vector['input'], true) => [$vector];
        }
    }

    /**
     * Shared with hesabe-python, hesabe-node and hesabe-swift; must stay byte-identical.
     *
     * @param array<string, mixed> $vector
     */
    #[DataProvider('vectors')]
    public function testSharedVector(array $vector): void
    {
        if ($vector['throws'] ?? false) {
            $this->expectException(\InvalidArgumentException::class);
            Amount::format($vector['input']);
            return;
        }
        self::assertSame($vector['expected'], Amount::format($vector['input']));
    }

    public function testFloatsUseTheirShortestRepresentation(): void
    {
        // 1.0005 is stored as 1.000499999...; formatting it at a fixed precision rounds down.
        self::assertSame('1.001', Amount::format(1.0005));
        self::assertSame('0.300', Amount::format(0.1 + 0.2));
    }

    public function testTheCallersPrecisionSettingIsRestored(): void
    {
        $previous = ini_get('serialize_precision');
        ini_set('serialize_precision', '17');
        try {
            self::assertSame('1.001', Amount::format(1.0005));
            self::assertSame('17', ini_get('serialize_precision'));
        } finally {
            ini_set('serialize_precision', (string) $previous);
        }
    }

    public function testHugeExponentsDoNotOverflow(): void
    {
        self::assertSame('0.000', Amount::format('1e-99999999999999999999'));
        $this->expectException(\InvalidArgumentException::class);
        Amount::format('1e99999999999999999999');
    }

    public function testRejectsInfinity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Amount::format(INF);
    }

    public function testCarriesAcrossEveryDigit(): void
    {
        self::assertSame('1000.000', Amount::format('999.9995'));
    }
}
