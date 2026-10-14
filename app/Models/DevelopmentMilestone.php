<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevelopmentMilestone extends Model
{
    protected $fillable = [
        'development_project_id',
        'title',
        'occurred_on',
        'note',
        'source_name',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'development_project_id');
    }
}
