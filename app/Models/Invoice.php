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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    // ── Lifecycle hooks ───────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Invoice $invoice) {
            if (! $invoice->invoice_number) {
                $invoice->invoice_number = 'DRAFT-' . uniqid();
            }
        });

        static::created(function (Invoice $invoice) {
            $invoice->updateQuietly([
                'invoice_number' => self::generateNumber($invoice),
            ]);
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
        $period = $invoice->period instanceof \Carbon\Carbon
            ? $invoice->period
            : \Carbon\Carbon::parse($invoice->period);

        return 'INV-' . $period->format('Ym') . '-' . str_pad($invoice->id, 6, '0', STR_PAD_LEFT);
    }
}
