<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'payout_method',
        'provider_name',
        'account_number',
        'account_holder',
        'notes',
    ];

    protected $casts = [
        'account_number' => 'encrypted',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Return masked account number showing only the last 4 digits (e.g. •••• 7890).
     */
    public function maskedAccountNumber(): string
    {
        $raw = (string) $this->account_number;
        if (strlen($raw) <= 4) {
            return str_repeat('•', strlen($raw));
        }

        $lastFour = substr($raw, -4);
        return '•••• ' . $lastFour;
    }
}

