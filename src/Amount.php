<?php

declare(strict_types=1);

namespace Hesabe;

/**
 * Formats money the one way Hesabe accepts: a string with exactly three decimals.
 *
 * KWD has three decimal places, and Hesabe answers 422 to `0.2005` (probed October 2026).
 * The arithmetic is done on decimal strings, half-up, because PHP floats cannot hold
 * 1.0005 exactly and bcmath is not guaranteed on a shop server. The accepted grammar and
 * every result match hesabe-python and hesabe-node through `fixtures/amount-vectors.json`.
 */
final class Amount
{
    /** The grammar the TypeScript twin accepts: no locale digits, separators or words. */
    private const GRAMMAR = '/^([+-]?)(?=[0-9]|\.[0-9])([0-9]*)(?:\.([0-9]*))?(?:[eE]([+-]?[0-9]+))?\z/';

    /**
     * JavaScript's trim() strips Unicode whitespace and the byte-order mark. (*UCP) makes \s
     * Unicode-aware, which PCRE's /u flag alone does not.
     */
    private const TRIM = '/(*UCP)^[\s\x{FEFF}]+|[\s\x{FEFF}]+\z/u';

    /**
     * hesabe-python's Decimal context cannot quantize beyond this, and hesabe-node would
     * allocate an unbounded BigInt. No KWD amount comes close.
     */
    private const MAX_ADJUSTED_EXPONENT = 24;

    /** Beyond this an exponent only decides "too large" or "rounds to zero". */
    private const EXPONENT_CLAMP = 1_000_000;

    /**
     * @throws \InvalidArgumentException when the value is not a non-negative decimal.
     */
    public static function format(string|int|float $amount): string
    {
        $text = self::text($amount);
        if (preg_match(self::GRAMMAR, $text, $match) !== 1) {
            throw new \InvalidArgumentException("Invalid amount: {$text}");
        }
        $sign = $match[1];
        $integer = $match[2];
        $fraction = $match[3] ?? '';
        $exponent = self::exponent($match[4] ?? '');

        $digits = ltrim($integer . $fraction, '0');
        if ($sign === '-' && $digits !== '') {
            throw new \InvalidArgumentException("Amounts must not be negative: {$text}");
        }

        // value = coefficient x 10^-scale, the same decomposition Python's Decimal uses, so
        // the adjusted-exponent limit rejects exactly what hesabe-python rejects.
        $coefficient = $digits === '' ? '0' : $digits;
        $scale = strlen($fraction) - $exponent;
        if (strlen($coefficient) - 1 - $scale > self::MAX_ADJUSTED_EXPONENT) {
            throw new \InvalidArgumentException("Invalid amount: {$text}");
        }

        $thousandths = self::roundToThousandths($coefficient, $scale);
        $padded = str_pad($thousandths, 4, '0', STR_PAD_LEFT);
        $whole = ltrim(substr($padded, 0, -3), '0');
        return ($whole === '' ? '0' : $whole) . '.' . substr($padded, -3);
    }

    private static function text(string|int|float $amount): string
    {
        if (is_float($amount)) {
            if (!is_finite($amount)) {
                throw new \InvalidArgumentException('Invalid amount: not a finite number');
            }
            // The shortest representation that round-trips, which is what Python's str() and
            // JavaScript's String() print. A fixed precision would turn 1.0005 into
            // 1.00049999... and round it down.
            $previous = ini_set('serialize_precision', '-1');
            try {
                $amount = json_encode($amount, JSON_THROW_ON_ERROR);
            } finally {
                if ($previous !== false) {
                    ini_set('serialize_precision', $previous);
                }
            }
        }
        return (string) preg_replace(self::TRIM, '', (string) $amount);
    }

    private static function exponent(string $text): int
    {
        if ($text === '') {
            return 0;
        }
        $negative = $text[0] === '-';
        $magnitude = ltrim($text, '+-0');
        if (strlen($magnitude) > 7) {
            return $negative ? -self::EXPONENT_CLAMP : self::EXPONENT_CLAMP;
        }
        $value = (int) $magnitude;
        return $negative ? -$value : $value;
    }

    /**
     * Returns round-half-up(coefficient x 10^(3 - scale)) as a digit string.
     */
    private static function roundToThousandths(string $coefficient, int $scale): string
    {
        $shift = 3 - $scale;
        if ($shift >= 0) {
            return $coefficient . str_repeat('0', $shift);
        }

        $drop = -$shift;
        $length = strlen($coefficient);
        if ($drop > $length) {
            // Every kept digit and the first dropped one are implicit leading zeros.
            return '0';
        }
        $kept = substr($coefficient, 0, $length - $drop);
        $kept = $kept === '' ? '0' : $kept;
        return $coefficient[$length - $drop] >= '5' ? self::increment($kept) : $kept;
    }

    private static function increment(string $digits): string
    {
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            if ($digits[$i] !== '9') {
                $digits[$i] = (string) ((int) $digits[$i] + 1);
                return $digits;
            }
            $digits[$i] = '0';
        }
        return '1' . $digits;
    }
}
