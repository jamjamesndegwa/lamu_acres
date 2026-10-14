<?php

namespace App\Models;

use App\Enums\DevelopmentCategory;
use App\Enums\DevelopmentProjectStatus;
use App\Models\Concerns\HasPublication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DevelopmentProject extends Model
{
    use HasPublication;

    protected $fillable = [
        'reference',
        'slug',
        'name',
        'category',
        'project_status',
        'coverage',
        'county_id',
        'locality_id',
        'summary',
        'research_notes',
        'latitude',
        'longitude',
        'boundary_wkt',
        'source_name',
        'source_url',
        'source_published_on',
        'verified_on',
        'publication_status',
        'published_at',
        'created_by',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            ...$this->publicationCasts(),
            'category' => DevelopmentCategory::class,
            'project_status' => DevelopmentProjectStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'source_published_on' => 'date',
            'verified_on' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(DevelopmentMilestone::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(IntelligenceFile::class, 'subject');
    }

    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }
}
