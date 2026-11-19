<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'pop_id',
        'marketing_user_id',
        'name',
        'phone',
        'address',
        'latitude',
        'longitude',
        'package',
        'billing_type',
        'notes',
        'status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'status'      => ApplicationStatus::class,
        'latitude'    => 'float',
        'longitude'   => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function marketingUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marketing_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    /**
     * Scope query to records visible to given user.
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

        if ($user->role === \App\Enums\Role::MARKETING) {
            return $query->where('marketing_user_id', $user->id);
        }

        return $query->whereRaw('1 = 0');
    }
}

