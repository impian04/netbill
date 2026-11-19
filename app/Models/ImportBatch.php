<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportBatch extends Model
{
    protected $fillable = [
        'user_id',
        'pop_id',
        'file_path',
        'original_name',
        'duplicate_policy',
        'status',
        'total_rows',
        'processed_rows',
        'created_count',
        'updated_count',
        'skipped_count',
        'error_count',
        'errors',
        'started_at',
        'finished_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function pop()
    {
        return $this->belongsTo(Pop::class);
    }

    protected function casts(): array
    {
        return [
            'errors'       => 'array',
            'started_at'   => 'datetime',
            'finished_at'  => 'datetime',
            'total_rows'   => 'integer',
            'processed_rows' => 'integer',
            'created_count'  => 'integer',
            'updated_count'  => 'integer',
            'skipped_count'  => 'integer',
            'error_count'    => 'integer',
        ];
    }

    public function isRunning(): bool
    {
        return in_array($this->status, ['queued', 'processing']);
    }

    public function progressPercent(): int
    {
        if (! $this->total_rows || $this->total_rows === 0) {
            return 0;
        }

        return (int) min(100, round($this->processed_rows / $this->total_rows * 100));
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'queued'     => 'Menunggu',
            'processing' => 'Memproses',
            'done'       => 'Selesai',
            'failed'     => 'Gagal',
            default      => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'queued'     => 'secondary',
            'processing' => 'primary',
            'done'       => $this->error_count > 0 ? 'warning' : 'success',
            'failed'     => 'danger',
            default      => 'secondary',
        };
    }
}
