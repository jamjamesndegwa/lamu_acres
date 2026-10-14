<?php

namespace App\Models;

use App\Enums\DocumentCheckState;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['property_id', 'code', 'state'])]
class PropertyDocumentCheck extends Model
{
    protected function casts(): array
    {
        return [
            'state' => DocumentCheckState::class,
        ];
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function label(): string
    {
        return match ($this->code) {
            'title_search' => 'Title search',
            'survey_plan' => 'Survey plan',
            default => $this->code,
        };
    }
}
