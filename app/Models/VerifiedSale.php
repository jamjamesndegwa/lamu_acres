<?php

namespace App\Models;

use App\Models\Concerns\HasPublication;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class VerifiedSale extends Model
{
    use HasPublication;

    protected $fillable = [
        'reference',
        'locality_id',
        'acreage',
        'price_amount',
        'price_currency',
        'sold_on',
        'source_name',
        'note',
        'publication_status',
        'published_at',
        'created_by',
        'reviewed_by',
    ];

    protected function casts(): array
    {
        return [
            ...$this->publicationCasts(),
            'acreage' => 'decimal:4',
            'price_amount' => 'integer',
            'sold_on' => 'date',
        ];
    }

    public function locality(): BelongsTo
    {
        return $this->belongsTo(Locality::class);
    }

    public function files(): MorphMany
    {
        return $this->morphMany(IntelligenceFile::class, 'subject');
    }

    public function pricePerAcre(): ?int
    {
        if ((float) $this->acreage <= 0) {
            return null;
        }

        return (int) round($this->price_amount / (float) $this->acreage);
    }
}
