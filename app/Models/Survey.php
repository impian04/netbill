<?php

namespace App\Models;

use App\Enums\SurveyResult;
use App\Enums\SurveyStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Survey extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'pop_id',
        'surveyor_user_id',
        'odc_id',
        'odp_id',
        'latitude',
        'longitude',
        'result',
        'result_note',
        'status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'customer_id'      => 'integer',
            'pop_id'           => 'integer',
            'surveyor_user_id' => 'integer',
            'odc_id'           => 'integer',
            'odp_id'           => 'integer',
            'latitude'         => 'float',
            'longitude'        => 'float',
            'result'           => SurveyResult::class,
            'status'           => SurveyStatus::class,
            'reviewed_by'      => 'integer',
            'reviewed_at'      => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyor_user_id');
    }

    public function odc(): BelongsTo
    {
        return $this->belongsTo(Odc::class);
    }

    public function odp(): BelongsTo
    {
        return $this->belongsTo(Odp::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SurveyItem::class);
    }

    public function workOrder(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query;
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isAdmin()) {
            return $query->where('surveys.pop_id', $user->pop_id);
        }

        if ($user->isSurveyor()) {
            return $query->where('surveys.surveyor_user_id', $user->id)
                ->where('surveys.pop_id', $user->pop_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function isEditableBy(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $this->surveyor_user_id === (int) $user->id
            && $this->status->isEditableBySurveyor();
    }
}

