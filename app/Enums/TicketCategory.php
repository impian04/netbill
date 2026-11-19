<?php

namespace App\Enums;

enum TicketCategory: string
{
    case CONNECTION   = 'CONNECTION';
    case PAYMENT      = 'PAYMENT';
    case INSTALLATION = 'INSTALLATION';
    case OTHER        = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::CONNECTION   => 'Gangguan Koneksi',
            self::PAYMENT      => 'Pembayaran & Tagihan',
            self::INSTALLATION => 'Instalasi & Perangkat',
            self::OTHER        => 'Lainnya',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::CONNECTION   => 'danger',
            self::PAYMENT      => 'warning',
            self::INSTALLATION => 'info',
            self::OTHER        => 'secondary',
        };
    }
}

