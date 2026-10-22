<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StockUnit: string
{
    use HasOptions;

    case Unit = 'unit';
    case Liter = 'liter';

    public function label(): string
    {
        return match ($this) {
            self::Unit => 'À l’unité',
            self::Liter => 'Au litre',
        };
    }

    /**
     * Suffixe affiché après une quantité : « 12 u », « 4,5 L ».
     */
    public function symbol(): string
    {
        return $this === self::Liter ? 'L' : 'u';
    }
}
