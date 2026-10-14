<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchCitation extends Model
{
    protected $fillable = [
        'research_report_id',
        'label',
        'url',
        'retrieved_on',
    ];

    protected function casts(): array
    {
        return [
            'retrieved_on' => 'date',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(ResearchReport::class, 'research_report_id');
    }
}
