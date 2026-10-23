<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum ImmobilizationUnit: string
{
    use HasOptions;

    case Hours = 'hours';
    case Days = 'days';

    public function label(): string
    {
        return match ($this) {
            self::Hours => 'Heures',
            self::Days => 'Jours',
        };
    }
}
