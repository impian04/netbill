<?php

namespace App\Enums;

enum TicketStatus: string
{
    case OPEN             = 'OPEN';
    case IN_PROGRESS      = 'IN_PROGRESS';
    case WAITING_CUSTOMER = 'WAITING_CUSTOMER';
    case FIELD_VISIT      = 'FIELD_VISIT';
    case RESOLVED         = 'RESOLVED';
    case CLOSED           = 'CLOSED';

    public function label(): string
    {
        return match ($this) {
            self::OPEN             => 'Terbuka',
            self::IN_PROGRESS      => 'Sedang Diproses',
            self::WAITING_CUSTOMER => 'Menunggu Pelanggan',
            self::FIELD_VISIT      => 'Kunjungan Lapangan',
            self::RESOLVED         => 'Terselesaikan',
            self::CLOSED           => 'Ditutup',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::OPEN             => 'primary',
            self::IN_PROGRESS      => 'info',
            self::WAITING_CUSTOMER => 'warning',
            self::FIELD_VISIT      => 'secondary',
            self::RESOLVED         => 'success',
            self::CLOSED           => 'dark',
        };
    }

    /**
     * Daftar status yang diizinkan untuk transisi dari status saat ini.
     *
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::OPEN             => [self::IN_PROGRESS],
            self::IN_PROGRESS      => [self::WAITING_CUSTOMER, self::FIELD_VISIT, self::RESOLVED],
            self::WAITING_CUSTOMER => [self::IN_PROGRESS, self::FIELD_VISIT, self::RESOLVED],
            self::FIELD_VISIT      => [self::IN_PROGRESS, self::RESOLVED],
            self::RESOLVED         => [self::IN_PROGRESS, self::CLOSED],
            self::CLOSED           => [],
        };
    }

    public function canTransitionTo(self|string $target): bool
    {
        $targetEnum = $target instanceof self ? $target : self::tryFrom($target);
        if (! $targetEnum) {
            return false;
        }

        return in_array($targetEnum, $this->allowedTransitions(), true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::RESOLVED, self::CLOSED], true);
    }
}

