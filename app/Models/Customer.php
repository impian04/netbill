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
        'pop_id',
        'odc_id',
        'odp_id',
        'customer_code',
        'name',
        'phone',
        'address',
        'latitude',
        'longitude',
        'subscribed_at',
        'package',
        'billing_type',
        'service_status',
        'registered_via',
        // NOTE: ktp_path and referred_by_user_id are NOT mass-assignable
        // NOTE: password, activation_code_hash, activated_at,
        //       activation_expires_at, activation_attempts are NOT mass-assignable
    ];

    protected $hidden = ['password', 'activation_code_hash'];

    protected function casts(): array
    {
        return [
            'pop_id'                  => 'integer',
            'odc_id'                  => 'integer',
            'odp_id'                  => 'integer',
            'latitude'                => 'float',
            'longitude'               => 'float',
            'subscribed_at'           => 'date',
            'activated_at'            => 'datetime',
            'activation_expires_at'   => 'datetime',
            'activation_locked_until' => 'datetime',
            'activation_attempts'     => 'integer',
            'password'                => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (Customer $customer) {
            if ($customer->ktp_path) {
                \App\Services\PrivateFileService::delete($customer->ktp_path);
            }
        });

        static::saved(function (Customer $customer) {
            if ($customer->service_status === 'ACTIVE') {
                if ($customer->wasRecentlyCreated || $customer->wasChanged('service_status')) {
                    \App\Services\MarketingFeeService::processEligibility($customer);
                }
            }
        });
    }

    public function pop(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function odc(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Odc::class);
    }

    public function odp(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Odp::class);
    }

    public function survey(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Survey::class);
    }

    public function workOrder(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(WorkOrder::class)->where('type', \App\Enums\WorkOrderType::INSTALLATION);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function referredBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeVisibleTo(\Illuminate\Database\Eloquent\Builder $query, ?User $user): \Illuminate\Database\Eloquent\Builder
    {
        if (! $user) {
            return $query;
        }

        if ($user->role === \App\Enums\Role::SUPER_ADMIN) {
            return $query;
        }

        return $query->where('customers.pop_id', $user->pop_id);
    }

    // ── Activation helpers ────────────────────────────────────────────

    public function isActivated(): bool
    {
        return $this->activated_at !== null && $this->password !== null;
    }

    public function activationStatus(): string
    {
        if ($this->activation_attempts >= \App\Services\ActivationCodeService::MAX_ATTEMPTS) {
            return 'locked_permanent';
        }
        if ($this->activation_locked_until && $this->activation_locked_until->isFuture()) {
            return 'locked_temporary';
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
            'none'             => 'Belum Aktivasi',
            'pending'          => 'Kode Aktif s.d. ' . $this->activation_expires_at?->locale('id')->isoFormat('D MMM YYYY HH:mm'),
            'expired'          => 'Kode Kedaluwarsa',
            'locked_temporary' => 'Terkunci sementara s.d. ' . $this->activation_locked_until?->locale('id')->isoFormat('HH:mm'),
            'locked_permanent' => 'Terkunci, butuh kode baru',
            'active'           => 'Sudah Aktif',
            default            => '-',
        };
    }

    public function activationStatusColor(): string
    {
        return match ($this->activationStatus()) {
            'none'             => 'secondary',
            'pending'          => 'info',
            'expired'          => 'warning',
            'locked_temporary' => 'warning',
            'locked_permanent' => 'danger',
            'active'           => 'success',
            default            => 'secondary',
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
