<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingFee extends Model
{
    use HasFactory;

    protected $fillable = [
        'marketing_user_id',
        'customer_id',
        'pop_id',
        'amount',
        'status',
        'eligible_at',
        'paid_at',
        'paid_note',
    ];

    protected $casts = [
        'amount'      => 'integer',
        'eligible_at' => 'datetime',
        'paid_at'     => 'datetime',
    ];

    public function marketingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketing_user_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    /**
     * Scope query to fees visible to given user (Admin scoped to POP, Super Admin sees all).
     */
    public function scopeVisibleTo(Builder $query, ?User $user = null): Builder
    {
        $user = $user ?? auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdmin()) {
            return $query->where('pop_id', $user->pop_id);
        }

        return $query->whereRaw('1 = 0');
    }
}

