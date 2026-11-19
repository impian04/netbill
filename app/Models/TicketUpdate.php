<?php

namespace App\Models;

use App\Enums\TicketStatus;
use App\Enums\TicketUpdateType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketUpdate extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'type',
        'body',
        'from_status',
        'to_status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'ticket_id'   => 'integer',
            'user_id'     => 'integer',
            'type'        => TicketUpdateType::class,
            'from_status' => TicketStatus::class,
            'to_status'   => TicketStatus::class,
            'created_at'  => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

