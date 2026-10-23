<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Utilisé par la paie (phase 10).
 */
enum SalaryType: string
{
    use HasOptions;

    case Monthly = 'monthly';
    case Daily = 'daily';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensuel',
            self::Daily => 'Journalier',
        };
    }
}
