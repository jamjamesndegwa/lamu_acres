<?php

namespace App\Models;

use App\Models\Concerns\HasPublication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageryComparison extends Model
{
    use HasPublication;

    protected $fillable = [
        'reference',
        'slug',
        'area_label',
        'before_scene_id',
        'after_scene_id',
        'development_project_id',
        'observed_change',
        'annotation',
        'publication_status',
        'published_at',
        'created_by',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return $this->publicationCasts();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function beforeScene(): BelongsTo
    {
        return $this->belongsTo(ImageryScene::class, 'before_scene_id');
    }

    public function afterScene(): BelongsTo
    {
        return $this->belongsTo(ImageryScene::class, 'after_scene_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'development_project_id');
    }
}
