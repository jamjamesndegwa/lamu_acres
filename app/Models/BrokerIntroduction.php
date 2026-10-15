<?php

namespace App\Models;

use App\Enums\BrokerIntroductionStatus;
use Database\Factories\BrokerIntroductionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'reference',
    'broker_id',
    'buyer_id',
    'buyer_name',
    'buyer_email',
    'buyer_phone',
    'property_id',
    'status',
    'introduced_at',
    'expires_at',
    'consent_note',
    'consent_path',
    'consent_disk',
    'evidence_path',
    'evidence_disk',
    'source',
    'commission_terms',
    'notes',
    'conflicts',
    'submitted_at',
    'reviewed_at',
    'reviewed_by',
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
            'conflicts' => 'array',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class, 'broker_introduction_property');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(BrokerIntroductionAudit::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function privateFile(string $kind): ?string
    {
        $path = match ($kind) {
            'consent' => $this->consent_path,
            'evidence' => $this->evidence_path,
            default => null,
        };

        if (! is_string($path) || $path === '' || str_contains($path, '..') || ! str_starts_with($path, 'broker-referrals/')) {
            return null;
        }

        return $path;
    }

    public function privateFileDisk(string $kind): string
    {
        return match ($kind) {
            'consent' => $this->consent_disk ?: 'local',
            'evidence' => $this->evidence_disk ?: 'local',
            default => 'local',
        };
    }

    public function protectsCommission(): bool
    {
        if (! $this->status->protectsCommission()) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
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
            ->whereIn('status', [BrokerIntroductionStatus::Active, BrokerIntroductionStatus::Accepted])
            ->where(function (Builder $builder): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });

        if ($property) {
            $query->where(function (Builder $builder) use ($property): void {
                $builder->whereNull('property_id')
                    ->orWhere('property_id', $property->id)
                    ->orWhereHas('properties', fn (Builder $related) => $related->where('properties.id', $property->id));
            });
        }

        return $query;
    }
}
