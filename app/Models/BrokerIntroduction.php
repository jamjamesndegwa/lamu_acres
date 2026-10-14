<?php

namespace App\Models;

use App\Enums\BrokerIntroductionStatus;
use Database\Factories\BrokerIntroductionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference',
    'broker_id',
    'buyer_id',
    'property_id',
    'status',
    'introduced_at',
    'expires_at',
])]
class BrokerIntroduction extends Model
{
    /** @use HasFactory<BrokerIntroductionFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => BrokerIntroductionStatus::class,
            'introduced_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'broker_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function scopeActiveForBuyer(Builder $query, User $buyer, ?Property $property = null): Builder
    {
        $query
            ->where('buyer_id', $buyer->id)
            ->where('status', BrokerIntroductionStatus::Active)
            ->where(function (Builder $builder): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($property) {
            $query->where(function (Builder $builder) use ($property): void {
                $builder->whereNull('property_id')->orWhere('property_id', $property->id);
            });
        }

        return $query;
    }
}
