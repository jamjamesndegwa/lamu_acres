<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['evidence_file_id', 'user_id', 'outcome', 'accessed_at'])]
class EvidenceAccessLog extends Model
{
    protected function casts(): array
    {
        return [
            'accessed_at' => 'datetime',
        ];
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(EvidenceFile::class, 'evidence_file_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
