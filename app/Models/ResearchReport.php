<?php

namespace App\Models;

use App\Models\Concerns\HasPublication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResearchReport extends Model
{
    use HasPublication;

    protected $fillable = [
        'reference',
        'slug',
        'title',
        'summary',
        'body',
        'report_date',
        'publication_status',
        'published_at',
        'created_by',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            ...$this->publicationCasts(),
            'report_date' => 'date',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function citations(): HasMany
    {
        return $this->hasMany(ResearchCitation::class);
    }

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(DevelopmentProject::class, 'report_projects');
    }

    public function localities(): BelongsToMany
    {
        return $this->belongsToMany(Locality::class, 'report_localities');
    }
}
