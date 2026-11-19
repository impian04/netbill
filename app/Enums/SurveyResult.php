<?php

namespace App\Enums;

enum SurveyResult: string
{
    case FEASIBLE     = 'FEASIBLE';
    case NOT_FEASIBLE = 'NOT_FEASIBLE';

    public function label(): string
    {
        return match ($this) {
            self::FEASIBLE     => 'Layak',
            self::NOT_FEASIBLE => 'Tidak Layak',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::FEASIBLE     => 'success',
            self::NOT_FEASIBLE => 'danger',
        };
    }
}

