<?php

namespace App\Models;

use App\Models\Concerns\HasPublication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ImageryScene extends Model
{
    use HasPublication;

    protected $fillable = [
        'reference',
        'provider',
        'acquired_on',
        'resolution',
        'coverage',
        'cloud_conditions',
        'license_name',
        'redistribution_allowed',
        'source_url',
        'county_id',
        'locality_id',
        'development_project_id',
        'latitude',
        'longitude',
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
            'acquired_on' => 'date',
            'redistribution_allowed' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'development_project_id');
    }

    public function files(): MorphMany
    {
        return $this->morphMany(IntelligenceFile::class, 'subject');
    }

    public function canRedistribute(): bool
    {
        return $this->redistribution_allowed && $this->isReleased();
    }
}
