<?php

namespace App\Enums;

enum TicketPriority: string
{
    case LOW    = 'LOW';
    case NORMAL = 'NORMAL';
    case HIGH   = 'HIGH';
    case URGENT = 'URGENT';

    public function label(): string
    {
        return match ($this) {
            self::LOW    => 'Rendah',
            self::NORMAL => 'Normal',
            self::HIGH   => 'Tinggi',
            self::URGENT => 'Mendesak',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::LOW    => 'secondary',
            self::NORMAL => 'info',
            self::HIGH   => 'warning',
            self::URGENT => 'danger',
        };
    }
}

