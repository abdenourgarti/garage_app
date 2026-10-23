<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum PartOrigin: string
{
    use HasOptions;

    /** Sort du stock à la fin de la prestation (RG-08). */
    case Stock = 'stock';

    /** Apportée par le client : historique seulement, ni prix ni stock. */
    case Client = 'client';

    /** Achetée à l'extérieur : prix d'achat en dépense, prix facturé au client. */
    case External = 'external';

    public function label(): string
    {
        return match ($this) {
            self::Stock => 'Stock',
            self::Client => 'Apportée par le client',
            self::External => 'Achetée à l’extérieur',
        };
    }
}
