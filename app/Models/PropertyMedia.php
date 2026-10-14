<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PropertyMedia extends Model
{
    protected $fillable = [
        'property_id',
        'uploaded_by',
        'kind',
        'disk',
        'path',
        'original_name',
        'mime',
        'position',
        'is_cover',
        'public_disk',
        'public_path',
        'video_url',
    ];

    protected function casts(): array
    {
        return [
            'is_cover' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function contents(): ?string
    {
        return Storage::disk($this->disk)->get($this->path);
    }
}
