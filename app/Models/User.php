<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'pop_id',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'role'              => Role::class,
            'is_active'         => 'boolean',
        ];
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function marketingProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MarketingProfile::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::ADMIN;
    }

    public function isSurveyor(): bool
    {
        return $this->role === Role::SURVEYOR;
    }

    public function isTechnician(): bool
    {
        return $this->role === Role::TECHNICIAN;
    }

    public function isNoc(): bool
    {
        return $this->role === Role::NOC;
    }

    public function isMarketing(): bool
    {
        return $this->role === Role::MARKETING;
    }

    public function hasRole(Role|string ...$roles): bool
    {
        $roleValues = array_map(fn ($r) => $r instanceof Role ? $r->value : $r, $roles);
        return in_array($this->role->value, $roleValues, true);
    }
}
