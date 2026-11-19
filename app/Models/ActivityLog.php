<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'pop_id',
        'action',
        'subject_type',
        'subject_id',
        'description',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ActivityLog $log) {
            if (! $log->created_at) {
                $log->created_at = now();
            }
        });

        static::updating(function () {
            throw new \RuntimeException('Activity logs are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \RuntimeException('Activity logs cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pop(): BelongsTo
    {
        return $this->belongsTo(Pop::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

