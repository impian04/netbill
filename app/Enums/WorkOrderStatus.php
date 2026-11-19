<?php

namespace App\Enums;

enum WorkOrderStatus: string
{
    case REQUESTED      = 'REQUESTED';
    case ASSIGNED       = 'ASSIGNED';
    case IN_PROGRESS    = 'IN_PROGRESS';
    case SUBMITTED      = 'SUBMITTED';
    case NEEDS_REVISION = 'NEEDS_REVISION';
    case VERIFIED       = 'VERIFIED';
    case CANCELLED      = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::REQUESTED      => 'Menunggu Penugasan',
            self::ASSIGNED       => 'Ditugaskan',
            self::IN_PROGRESS    => 'Dikerjakan',
            self::SUBMITTED      => 'Menunggu Verifikasi',
            self::NEEDS_REVISION => 'Perlu Perbaikan',
            self::VERIFIED       => 'Terverifikasi & Selesai',
            self::CANCELLED      => 'Dibatalkan',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::REQUESTED      => 'warning',
            self::ASSIGNED       => 'info',
            self::IN_PROGRESS    => 'primary',
            self::SUBMITTED      => 'warning',
            self::NEEDS_REVISION => 'danger',
            self::VERIFIED       => 'success',
            self::CANCELLED      => 'secondary',
        };
    }

    public function isEditableByTechnician(): bool
    {
        return in_array($this, [self::ASSIGNED, self::IN_PROGRESS, self::NEEDS_REVISION], true);
    }
}

