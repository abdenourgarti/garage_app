<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Brouillon → Commandée → Reçue (le stock entre à la réception) ; Annulée avant réception.
 */
enum PurchaseStatus: string
{
    use HasOptions;

    case Draft = 'draft';
    case Ordered = 'ordered';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Ordered => 'Commandée',
            self::Received => 'Reçue',
            self::Cancelled => 'Annulée',
        };
    }

    /**
     * Lines can be changed until the goods are received.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Ordered;
    }

    /**
     * A supplier can be paid once the order is placed (deposit) or received.
     */
    public function isPayable(): bool
    {
        return $this === self::Ordered || $this === self::Received;
    }
}
