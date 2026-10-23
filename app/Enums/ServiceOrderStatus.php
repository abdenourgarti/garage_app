<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * RG-05 : En cours (modifiable, supprimable par le gérant) → Terminée (figée).
 */
enum ServiceOrderStatus: string
{
    use HasOptions;

    case InProgress = 'en_cours';
    case Completed = 'terminee';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'En cours',
            self::Completed => 'Terminée',
        };
    }
}
