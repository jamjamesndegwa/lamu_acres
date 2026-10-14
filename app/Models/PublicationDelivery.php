<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PublicationDelivery extends Model
{
    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'research_assignment_id',
        'delivery_key',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ResearchAssignment::class, 'research_assignment_id');
    }
}
