<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Enums\ResearchCommitment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ResearchAssignment extends Model
{
    protected $fillable = [
        'commitment',
        'period_start',
        'period_end',
        'due_on',
        'status',
        'subject_type',
        'subject_id',
        'note',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'commitment' => ResearchCommitment::class,
            'status' => AssignmentStatus::class,
            'period_start' => 'date',
            'period_end' => 'date',
            'due_on' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
