<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurveyItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'survey_id',
        'material_id',
        'quantity',
    ];

    protected function casts(): array
    {
        return [
            'survey_id'   => 'integer',
            'material_id' => 'integer',
            'quantity'    => 'float',
        ];
    }

    public function survey(): BelongsTo
    {
        return $this->belongsTo(Survey::class);
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class);
    }
}

