<?php

namespace App\Models;

use App\Enums\TicketCategory;
use App\Enums\TicketChannel;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_number',
        'customer_id',
        'pop_id',
        'reporter_name',
        'reporter_phone',
        'category',
        'channel',
        'priority',
        'subject',
        'description',
        'status',
        'created_by',
        'assigned_to',
        'opened_at',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'customer_id'       => 'integer',
            'pop_id'            => 'integer',
            'category'          => TicketCategory::class,
            'channel'           => TicketChannel::class,
            'priority'          => TicketPriority::class,
            'status'            => TicketStatus::class,
            'created_by'        => 'integer',
            'assigned_to'       => 'integer',
            'opened_at'         => 'datetime',
            'first_response_at' => 'datetime',
            'resolved_at'       => 'datetime',
            'closed_at'         => 'datetime',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(TicketUpdate::class)->orderBy('created_at', 'asc');
    }

    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin() || $user->isNoc()) {
            return $query;
        }

        if ($user->isAdmin()) {
            return $query->where('tickets.pop_id', $user->pop_id);
        }

        return $query->whereRaw('1 = 0');
    }

    public function isEditableBy(User $user): bool
    {
        if ($this->status === TicketStatus::CLOSED) {
            return false;
        }

        return $user->isSuperAdmin() || $user->isNoc();
    }

    /**
     * Buat tiket dengan nomor tiket berformat TKT-YYYYMM-{id 6 digit} dalam transaksi.
     * Menjamin tidak ada nilai sementara tersisa saat commit.
     */
    public static function createWithNumber(array $attributes): self
    {
        return DB::transaction(function () use ($attributes) {
            $attributes['ticket_number'] = 'TMP-' . Str::random(12);
            $ticket = self::create($attributes);

            $ticket->ticket_number = sprintf('TKT-%s-%06d', now()->format('Ym'), $ticket->id);
            $ticket->save();

            return $ticket;
        });
    }
}

