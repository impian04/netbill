<?php

namespace App\Enums;

enum PhotoStage: string
{
    case INSTALASI = 'INSTALASI';
    case AKTIVASI  = 'AKTIVASI';
    case PERBAIKAN = 'PERBAIKAN';
    case LAINNYA   = 'LAINNYA';

    public function label(): string
    {
        return match ($this) {
            self::INSTALASI => 'Instalasi Fisik',
            self::AKTIVASI  => 'Aktivasi / Konfigurasi',
            self::PERBAIKAN => 'Perbaikan Fisik / Jaringan',
            self::LAINNYA   => 'Lainnya',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::INSTALASI => 'primary',
            self::AKTIVASI  => 'success',
            self::PERBAIKAN => 'warning',
            self::LAINNYA   => 'secondary',
        };
    }
}

