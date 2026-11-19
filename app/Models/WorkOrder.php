<?php

namespace App\Models;

use App\Enums\WorkOrderStatus;
use App\Enums\WorkOrderType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    use HasFactory;

    public const MIN_PHOTOS_INSTALLATION = 2;
    public const MIN_PHOTOS_REPAIR       = 1;
    public const MIN_PHOTOS              = 2;
    public const MAX_PHOTOS              = 12;

    protected $fillable = [
        'type',
        'customer_id',
        'installation_key',
        'survey_id',
        'ticket_id',
        'pop_id',
        'technician_user_id',
        'status',
        'scheduled_date',
        'admin_note',
        'technician_notes',
        'completed_on',
        'assigned_by',
        'review_note',
        'verified_by',
        'verified_at',
        'activated_on',
    ];

    protected function casts(): array
    {
        return [
            'type'               => WorkOrderType::class,
            'customer_id'        => 'integer',
            'installation_key'   => 'integer',
            'survey_id'          => 'integer',
            'ticket_id'          => 'integer',
            'pop_id'             => 'integer',
            'technician_user_id' => 'integer',
            'status'             => WorkOrderStatus::class,
            'scheduled_date'     => 'date',
            'completed_on'       => 'date',
            'activated_on'       => 'date',
            'assigned_by'        => 'integer',
            'verified_by'        => 'integer',
            'verified_at'        => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function isRepair(): bool
    {
        return $this->type === WorkOrderType::REPAIR;
    }

    public function isInstallation(): bool
    {
        return $this->type === WorkOrderType::INSTALLATION;
    }

    public function getMinPhotos(): int
    {
        return $this->isRepair() ? self::MIN_PHOTOS_REPAIR : self::MIN_PHOTOS_INSTALLATION;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_user_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(WorkOrderPhoto::class);
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
            return $query->where('work_orders.pop_id', $user->pop_id);
        }

        if ($user->isTechnician()) {
            return $query->where('work_orders.technician_user_id', $user->id)
                ->where('work_orders.pop_id', $user->pop_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function isEditableBy(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $this->technician_user_id === (int) $user->id
            && $this->status->isEditableByTechnician();
    }

    public function canBeReassigned(): bool
    {
        return $this->status !== WorkOrderStatus::VERIFIED
            && $this->status !== WorkOrderStatus::CANCELLED;
    }

    public function canBeVerified(): bool
    {
        return $this->status === WorkOrderStatus::SUBMITTED
            && $this->photos()->count() >= $this->getMinPhotos();
    }
}

