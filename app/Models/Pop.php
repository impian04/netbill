<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pop extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'va_code',
        'is_active',
        'last_customer_number',
    ];

    protected function casts(): array
    {
        return [
            'is_active'            => 'boolean',
            'last_customer_number' => 'integer',
        ];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function odcs(): HasMany
    {
        return $this->hasMany(Odc::class);
    }

    public function odps(): HasMany
    {
        return $this->hasMany(Odp::class);
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class);
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(Survey::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

