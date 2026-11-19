<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case SUBMITTED = 'SUBMITTED';
    case NEEDS_REVISION = 'NEEDS_REVISION';
    case APPROVED = 'APPROVED';
    case REJECTED = 'REJECTED';

    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Menunggu Verifikasi',
            self::NEEDS_REVISION => 'Perlu Perbaikan',
            self::APPROVED => 'Disetujui',
            self::REJECTED => 'Ditolak',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::SUBMITTED => 'warning',
            self::NEEDS_REVISION => 'info',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }
}

