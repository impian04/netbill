<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Model
{
    use HasApiTokens, HasFactory;

    public const SERVICE_STATUSES = ['PENDING', 'ACTIVE', 'SUSPENDED', 'INACTIVE'];

    protected $fillable = [
        'customer_code',
        'name',
        'phone',
        'address',
        'subscribed_at',
        'package',
        'billing_type',
        'service_status',
        // NOTE: password, activation_code_hash, activated_at,
        //       activation_expires_at, activation_attempts are NOT mass-assignable
    ];

    protected $hidden = ['password', 'activation_code_hash'];

    protected function casts(): array
    {
        return [
            'subscribed_at'         => 'date',
            'activated_at'          => 'datetime',
            'activation_expires_at' => 'datetime',
            'activation_attempts'   => 'integer',
            'password'              => 'hashed',
        ];
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // ── Activation helpers ────────────────────────────────────────────

    public function isActivated(): bool
    {
        return $this->activated_at !== null && $this->password !== null;
    }

    public function activationStatus(): string
    {
        if ($this->activation_attempts >= 5) {
            return 'locked';
        }
        if ($this->activated_at !== null && $this->password !== null) {
            return 'active';
        }
        if ($this->activation_code_hash !== null) {
            if ($this->activation_expires_at && $this->activation_expires_at->isPast()) {
                return 'expired';
            }
            return 'pending'; // code exists, not yet used
        }
        return 'none';
    }

    public function activationStatusLabel(): string
    {
        return match ($this->activationStatus()) {
            'none'    => 'Belum Aktivasi',
            'pending' => 'Kode Aktif s.d. ' . $this->activation_expires_at?->locale('id')->isoFormat('D MMM YYYY HH:mm'),
            'expired' => 'Kode Kedaluwarsa',
            'locked'  => 'Terkunci (5× salah)',
            'active'  => 'Sudah Aktif',
            default   => '-',
        };
    }

    public function activationStatusColor(): string
    {
        return match ($this->activationStatus()) {
            'none'    => 'secondary',
            'pending' => 'info',
            'expired' => 'warning',
            'locked'  => 'danger',
            'active'  => 'success',
            default   => 'secondary',
        };
    }

    public function serviceStatusColor(): string
    {
        return match ($this->service_status) {
            'ACTIVE'    => 'success',
            'PENDING'   => 'warning',
            'SUSPENDED' => 'danger',
            'INACTIVE'  => 'secondary',
            default     => 'secondary',
        };
    }
}
