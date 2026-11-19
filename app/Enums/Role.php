<?php

namespace App\Enums;

enum Role: string
{
    case SUPER_ADMIN = 'super_admin';
    case ADMIN       = 'admin';
    case MARKETING   = 'marketing';
    case SURVEYOR    = 'surveyor';
    case TECHNICIAN  = 'technician';
    case NOC         = 'noc';

    public function label(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN       => 'Admin POP',
            self::MARKETING   => 'Marketing',
            self::SURVEYOR    => 'Surveyor',
            self::TECHNICIAN  => 'Teknisi',
            self::NOC         => 'NOC',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'primary',
            self::ADMIN       => 'info',
            self::MARKETING   => 'success',
            self::SURVEYOR    => 'warning',
            self::TECHNICIAN  => 'secondary',
            self::NOC         => 'dark',
        };
    }

    public function requiresPop(): bool
    {
        return in_array($this, [self::ADMIN, self::MARKETING, self::SURVEYOR, self::TECHNICIAN], true);
    }
}

