<?php

namespace App\Models;

use App\Enums\PhotoStage;
use App\Services\PrivateFileService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_order_id',
        'path',
        'stage',
        'caption',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'work_order_id' => 'integer',
            'stage'         => PhotoStage::class,
            'uploaded_by'   => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (WorkOrderPhoto $photo) {
            if ($photo->path) {
                PrivateFileService::delete($photo->path);
            }
        });
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return route('files.work-orders.photo', $this);
    }
}

