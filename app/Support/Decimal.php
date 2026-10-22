<?php

namespace App\Support;

/**
 * Calculs exacts sur les montants et quantités (chaînes décimales, bcmath) :
 * jamais de flottants pour l'argent. Arrondi au plus proche, la moitié s'éloignant de zéro.
 */
final class Decimal
{
    /**
     * 2.5 × 1500.00 → « 3750.00 ».
     */
    public static function mul(string|int $a, string|int $b, int $scale = 2): string
    {
        return self::round(bcmul((string) $a, (string) $b, $scale + 4), $scale);
    }

    /**
     * 6500 ÷ 5 → « 1300.00 ».
     */
    public static function div(string|int $a, string|int $b, int $scale = 2): string
    {
        return self::round(bcdiv((string) $a, (string) $b, $scale + 4), $scale);
    }

    public static function add(string|int ...$values): string
    {
        return array_reduce($values, fn (string $sum, string|int $value) => bcadd($sum, (string) $value, 2), '0.00');
    }

    public static function sub(string|int $a, string|int $b): string
    {
        return bcsub((string) $a, (string) $b, 2);
    }

    /**
     * -1 si a < b, 0 si égaux, 1 si a > b.
     */
    public static function cmp(string|int $a, string|int $b): int
    {
        return bccomp((string) $a, (string) $b, 2);
    }

    public static function isZero(string|int $value): bool
    {
        return self::cmp($value, 0) === 0;
    }

    /**
     * Affichage dans un message : « 10.00 » → « 10 », « 4.50 » → « 4,5 ».
     */
    public static function display(string|int $value): string
    {
        $value = (string) $value;

        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return str_replace('.', ',', $value);
    }

    private static function round(string $value, int $scale): string
    {
        $half = '0.'.str_repeat('0', $scale).'5';

        return str_starts_with($value, '-')
            ? bcsub($value, $half, $scale)
            : bcadd($value, $half, $scale);
    }
}
