<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Formats de vente d'une huile : chaque format est un article avec son prix et son stock (RG-09).
 */
enum OilFormat: string
{
    use HasOptions;

    case Can5L = 'can_5l';
    case Can4L = 'can_4l';
    case Can1L = 'can_1l';
    case Bulk = 'bulk';

    public function label(): string
    {
        return match ($this) {
            self::Can5L => 'Bidon 5 L',
            self::Can4L => 'Bidon 4 L',
            self::Can1L => 'Bidon 1 L',
            self::Bulk => 'Vrac',
        };
    }

    /**
     * Dans une phrase : « bidon 5 L », « vrac » (l'unité garde sa majuscule).
     */
    public function inlineLabel(): string
    {
        $label = $this->label();

        return mb_strtolower(mb_substr($label, 0, 1)).mb_substr($label, 1);
    }

    /**
     * Litres contenus dans un bidon ; null pour le vrac.
     */
    public function volume(): ?string
    {
        return match ($this) {
            self::Can5L => '5.00',
            self::Can4L => '4.00',
            self::Can1L => '1.00',
            self::Bulk => null,
        };
    }

    public function stockUnit(): StockUnit
    {
        return $this === self::Bulk ? StockUnit::Liter : StockUnit::Unit;
    }

    public function isCan(): bool
    {
        return $this !== self::Bulk;
    }
}
