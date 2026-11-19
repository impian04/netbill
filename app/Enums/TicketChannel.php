<?php

namespace App\Enums;

enum TicketChannel: string
{
    case WHATSAPP = 'WHATSAPP';
    case PHONE    = 'PHONE';
    case WALK_IN  = 'WALK_IN';
    case APP      = 'APP';
    case OTHER    = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::WHATSAPP => 'WhatsApp',
            self::PHONE    => 'Telepon',
            self::WALK_IN  => 'Walk-in / Kantor',
            self::APP      => 'Aplikasi',
            self::OTHER    => 'Lainnya',
        };
    }
}

