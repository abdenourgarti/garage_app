<?php

namespace App\Enums;

/**
 * Catégories de dépenses utilisées par les Actions (achats, paie, pièces extérieures).
 */
enum SystemExpenseCategory: string
{
    case Purchases = 'purchases';
    case Salaries = 'salaries';
    case Advances = 'advances';
    case ExternalParts = 'external_parts';

    public function label(): string
    {
        return match ($this) {
            self::Purchases => 'Achats pièces',
            self::Salaries => 'Salaires',
            self::Advances => 'Avances sur salaire',
            self::ExternalParts => "Pièces achetées à l'extérieur",
        };
    }
}
