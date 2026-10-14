<?php

namespace App\Models;

use App\Enums\PlanningInstrumentStatus;
use App\Models\Concerns\HasPublication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PlanningRecord extends Model
{
    use HasPublication;

    protected $fillable = [
        'reference',
        'slug',
        'title',
        'area',
        'county_id',
        'locality_id',
        'instrument_status',
        'permitted_uses',
        'restrictions',
        'effective_on',
        'source_document',
        'source_url',
        'map_reference',
        'notes',
        'publication_status',
        'published_at',
        'created_by',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            ...$this->publicationCasts(),
            'instrument_status' => PlanningInstrumentStatus::class,
            'effective_on' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(IntelligenceFile::class, 'subject');
    }
}
