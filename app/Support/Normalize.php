<?php

namespace App\Support;

/**
 * Formes « normalisées » servant à l'unicité et à la recherche (RG-03).
 */
class Normalize
{
    /**
     * « 0550 12 34 56 », « +213 550 12 34 56 » et « 00213550123456 » → « 0550123456 ».
     */
    public static function phone(?string $phone): string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        return preg_replace('/^(00)?213/', '0', $digits);
    }

    /**
     * « 01234-118-16 » → « 0123411816 » ; lettres conservées en majuscules (anciennes plaques, étrangères).
     */
    public static function plate(?string $plate): string
    {
        return mb_strtoupper(preg_replace('/[^\p{L}\p{N}]+/u', '', (string) $plate));
    }

    /**
     * Châssis : majuscules sans espaces ; vide → null (le VIN est facultatif mais unique).
     */
    public static function vin(?string $vin): ?string
    {
        $vin = mb_strtoupper(preg_replace('/\s+/', '', (string) $vin));

        return $vin === '' ? null : $vin;
    }
}
