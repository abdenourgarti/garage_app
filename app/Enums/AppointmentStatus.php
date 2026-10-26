<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

/**
 * Prévu → Effectué (« Commencer » crée la prestation) ou Annulé (client absent). Effectué et Annulé sont définitifs.
 */
enum AppointmentStatus: string
{
    use HasOptions;

    case Planned = 'prevu';
    case Done = 'effectue';
    case Cancelled = 'annule';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'Prévu',
            self::Done => 'Effectué',
            self::Cancelled => 'Annulé',
        };
    }
}
