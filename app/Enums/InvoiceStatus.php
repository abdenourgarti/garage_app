<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * RG-12 / RG-13 : le statut suit les paiements ; Annulée est posée par le gérant seulement.
 */
enum InvoiceStatus: string
{
    use HasOptions;

    case Unpaid = 'impayee';
    case Partial = 'partielle';
    case Paid = 'payee';
    case Cancelled = 'annulee';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Impayée',
            self::Partial => 'Partielle',
            self::Paid => 'Payée',
            self::Cancelled => 'Annulée',
        };
    }
}
