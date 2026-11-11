<?php

namespace App\Filters;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class CustomerFilter
{
    private const SORTABLE = ['customer_code', 'name', 'subscribed_at', 'package'];

    public static function apply(Builder $query, array $filters): Builder
    {
        $search = $filters['search'] ?? null;
        if (is_array($search)) {
            $search = $search['value'] ?? null;
        }
        self::search($query, is_string($search) && trim($search) !== '' ? trim($search) : null);
        self::filterPackage($query, $filters['package'] ?? null);
        self::filterBillingType($query, $filters['billing_type'] ?? null);
        self::filterServiceStatus($query, $filters['service_status'] ?? null);
        self::filterByInvoice(
            $query,
            $filters['period'] ?? null,
            $filters['year'] ?? null,
            $filters['status'] ?? null
        );
        self::sort($query, $filters['sort'] ?? 'customer_code', $filters['direction'] ?? 'asc');

        return $query;
    }

    private static function search(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }
        $query->where(function (Builder $q) use ($search) {
            $q->where('customer_code', 'like', $search . '%')
                ->orWhere('name', 'like', '%' . $search . '%')
                ->orWhere('address', 'like', '%' . $search . '%');
        });
    }

    private static function filterPackage(Builder $query, ?string $package): void
    {
        if ($package) {
            $query->where('package', $package);
        }
    }

    private static function filterBillingType(Builder $query, ?string $billingType): void
    {
        if ($billingType) {
            $query->where('billing_type', $billingType);
        }
    }

    private static function filterServiceStatus(Builder $query, ?string $status): void
    {
        if ($status && in_array($status, ['PENDING', 'ACTIVE', 'SUSPENDED', 'INACTIVE'], true)) {
            $query->where('service_status', $status);
        }
    }

    private static function filterByInvoice(
        Builder $query,
        ?string $period,
        ?string $year,
        ?string $status
    ): void {
        $hasPeriod = $period && preg_match('/^\d{4}-\d{2}$/', $period);
        $hasYear   = $year && preg_match('/^\d{4}$/', $year);
        $hasStatus = in_array($status, ['PAID', 'UNPAID'], true);

        if (! $hasPeriod && ! $hasYear && ! $hasStatus) {
            return;
        }

        $query->whereHas('invoices', function (Builder $q) use ($hasPeriod, $period, $hasYear, $year, $hasStatus, $status) {
            if ($hasPeriod) {
                $q->where('period', Carbon::createFromFormat('Y-m', $period)->startOfMonth()->format('Y-m-d'));
            }
            if ($hasYear) {
                $q->whereYear('period', (int) $year);
            }
            if ($hasStatus) {
                $q->where('status', $status);
            }
        });
    }

    private static function sort(Builder $query, string $sort, string $direction): void
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';
        $sort      = in_array($sort, self::SORTABLE, true) ? $sort : 'customer_code';
        $query->orderBy($sort, $direction);
    }
}
