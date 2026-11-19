<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Material extends Model
{
    use HasFactory;

    public const UNITS = ['meter', 'pcs', 'unit', 'roll', 'set'];

    protected $fillable = [
        'pop_id',
        'name',
        'unit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'pop_id'    => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query;
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where('materials.pop_id', $user->pop_id);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('materials.is_active', true);
    }

    public function isUsed(): bool
    {
        // Di fase mendatang material mungkin terikat pada instalasi / transaksi logistik
        return false;
    }
}
