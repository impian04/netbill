<?php

namespace App\Filters;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class InvoiceFilter
{
    private const SORTABLE = ['period', 'deferred_amount', 'status'];

    public static function apply(Builder $query, array $filters): Builder
    {
        // Optional: filter by customer code / name (used by export from show page)
        $search = $filters['search'] ?? null;
        if ($search) {
            $query->whereHas('customer', function (Builder $q) use ($search) {
                $q->where('customer_code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $status = $filters['status'] ?? null;
        if (in_array($status, ['PAID', 'UNPAID'], true)) {
            $query->where('status', $status);
        }

        $period = $filters['period'] ?? null;
        if ($period && preg_match('/^\d{4}-\d{2}$/', $period)) {
            $query->where('period', Carbon::createFromFormat('Y-m', $period)->startOfMonth()->format('Y-m-d'));
        }

        $year = $filters['year'] ?? null;
        if ($year && preg_match('/^\d{4}$/', $year)) {
            $query->whereYear('period', (int) $year);
        }

        $sort      = $filters['sort'] ?? 'period';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $sort      = in_array($sort, self::SORTABLE, true) ? $sort : 'period';
        $query->orderBy($sort, $direction);

        return $query;
    }
}
