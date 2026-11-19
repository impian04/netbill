<?php

namespace App\Enums;

enum SurveyStatus: string
{
    case SUBMITTED           = 'SUBMITTED';
    case RESURVEY_REQUESTED = 'RESURVEY_REQUESTED';
    case ASSIGNED            = 'ASSIGNED';
    case CLOSED              = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED           => 'Menunggu Peninjauan',
            self::RESURVEY_REQUESTED => 'Perlu Survei Ulang',
            self::ASSIGNED            => 'Ditugaskan ke Teknisi',
            self::CLOSED              => 'Ditutup (Batal)',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::SUBMITTED           => 'info',
            self::RESURVEY_REQUESTED => 'warning',
            self::ASSIGNED            => 'primary',
            self::CLOSED              => 'secondary',
        };
    }

    public function isEditableBySurveyor(): bool
    {
        return in_array($this, [self::SUBMITTED, self::RESURVEY_REQUESTED], true);
    }
}

