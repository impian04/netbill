<?php

namespace App\Enums;

enum WorkOrderType: string
{
    case INSTALLATION = 'INSTALLATION';
    case REPAIR       = 'REPAIR';

    public function label(): string
    {
        return match ($this) {
            self::INSTALLATION => 'Instalasi Baru',
            self::REPAIR       => 'Perbaikan (Repair)',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::INSTALLATION => 'primary',
            self::REPAIR       => 'warning',
        };
    }
}

