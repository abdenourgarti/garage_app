<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum StockMovementType: string
{
    use HasOptions;

    case PurchaseIn = 'purchase_in';
    case ServiceOut = 'service_out';
    case Adjustment = 'adjustment';
    case CanOpenedOut = 'can_opened_out';
    case CanOpenedIn = 'can_opened_in';
    case CancellationIn = 'cancellation_in';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseIn => 'Achat reçu',
            self::ServiceOut => 'Sortie prestation',
            self::Adjustment => 'Correction',
            self::CanOpenedOut => 'Bidon ouvert',
            self::CanOpenedIn => 'Vrac (bidon ouvert)',
            self::CancellationIn => 'Retour (facture annulée)',
        };
    }
}
