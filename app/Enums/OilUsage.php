<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum OilUsage: string
{
    use HasOptions;

    case Engine = 'moteur';
    case Gearbox = 'boite';

    public function label(): string
    {
        return match ($this) {
            self::Engine => 'Moteur',
            self::Gearbox => 'Boîte de vitesses',
        };
    }
}
