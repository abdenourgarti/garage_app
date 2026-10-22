<?php

namespace App\Enums;

enum UserRole: string
{
    case Gerant = 'gerant';
    case Employe = 'employe';

    public function label(): string
    {
        return match ($this) {
            self::Gerant => 'Gérant',
            self::Employe => 'Employé',
        };
    }
}
