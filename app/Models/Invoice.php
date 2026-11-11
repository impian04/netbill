<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'period',
        'deferred_amount',
        'status',
        'due_date',
        'paid_at',
        'payment_reference',
        // invoice_number is auto-generated, NOT mass-assignable
    ];

    protected function casts(): array
    {
        return [
            'period'          => 'date',
            'due_date'        => 'date',
            'paid_at'         => 'datetime',
            'deferred_amount' => 'integer',
        ];
    }

    public function setPeriodAttribute($value): void
    {
        if (! $value) {
            $this->attributes['period'] = null;
            return;
        }

        $date = $value instanceof \Carbon\CarbonInterface
            ? $value
            : \Carbon\Carbon::parse($value);

        $this->attributes['period'] = $date->startOfMonth()->format('Y-m-d');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // ── Lifecycle hooks ───────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (! $invoice->invoice_number) {
                $invoice->invoice_number = 'DRAFT-' . uniqid() . '-' . bin2hex(random_bytes(4));
            }
        });

        static::created(function (Invoice $invoice) {
            if (str_starts_with((string) $invoice->invoice_number, 'DRAFT-')) {
                $invoice->forceFill([
                    'invoice_number' => self::generateNumber($invoice),
                ])->saveQuietly();
            }
        });

        static::updating(function (Invoice $invoice) {
            if ($invoice->isDirty('status')) {
                if ($invoice->status === 'PAID') {
                    // Set paid_at only if not already set
                    if (! $invoice->paid_at) {
                        $invoice->paid_at = now();
                    }
                } else {
                    // Reverted to UNPAID — clear payment info
                    $invoice->paid_at             = null;
                    $invoice->payment_reference   = null;
                }
            }
        });
    }

    public static function generateNumber(Invoice $invoice): string
    {
        $period = $invoice->period instanceof \Carbon\CarbonInterface
            ? $invoice->period
            : \Carbon\Carbon::parse($invoice->period);

        return 'INV-' . $period->format('Ym') . '-' . str_pad((string) $invoice->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Ganti semua invoice_number berawalan DRAFT- menjadi format resmi INV-YYYYMM-{id 6 digit}.
     */
    public static function fixDraftNumbers(?array $ids = null): int
    {
        $query = self::where('invoice_number', 'like', 'DRAFT-%');
        if ($ids !== null && count($ids) > 0) {
            $query->whereIn('id', $ids);
        }

        $count = 0;
        $query->chunkById(100, function ($invoices) use (&$count) {
            foreach ($invoices as $invoice) {
                $invoice->forceFill([
                    'invoice_number' => self::generateNumber($invoice),
                ])->saveQuietly();
                $count++;
            }
        });

        return $count;
    }
}
