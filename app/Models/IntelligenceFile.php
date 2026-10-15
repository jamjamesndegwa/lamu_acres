<?php

namespace App\Models;

use App\Models\Concerns\StoresFileUrls;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IntelligenceFile extends Model
{
    use StoresFileUrls;

    protected $fillable = [
        'subject_type',
        'subject_id',
        'kind',
        'disk',
        'path',
        'original_name',
        'mime',
        'size_bytes',
        'uploaded_by',
    ];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
