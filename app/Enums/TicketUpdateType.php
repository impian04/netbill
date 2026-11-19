<?php

namespace App\Enums;

enum TicketUpdateType: string
{
    case NOTE          = 'NOTE';
    case STATUS_CHANGE = 'STATUS_CHANGE';
    case ASSIGNMENT    = 'ASSIGNMENT';

    public function label(): string
    {
        return match ($this) {
            self::NOTE          => 'Catatan',
            self::STATUS_CHANGE => 'Perubahan Status',
            self::ASSIGNMENT    => 'Penugasan',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::NOTE          => 'primary',
            self::STATUS_CHANGE => 'warning',
            self::ASSIGNMENT    => 'info',
        };
    }
}

