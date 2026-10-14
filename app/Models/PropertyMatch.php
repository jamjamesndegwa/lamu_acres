<?php

namespace App\Models;

use App\Enums\PropertyMatchStatus;
use App\Enums\SiteVisitStatus;
use Database\Factories\PropertyMatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'buyer_id',
    'property_id',
    'sourcing_request_id',
    'enquiry_id',
    'approved_by',
    'assigned_to',
    'status',
    'buyer_feedback',
    'approved_at',
])]
class PropertyMatch extends Model
{
    /** @use HasFactory<PropertyMatchFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PropertyMatchStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function sourcingRequest(): BelongsTo
    {
        return $this->belongsTo(SourcingRequest::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(SiteVisit::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentAccessRequest::class);
    }

    public function timeline(): HasMany
    {
        return $this->hasMany(BuyerTimelineEntry::class)->orderByDesc('occurred_at');
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->buyer_id === (int) $user->id;
    }

    public function directionsReleased(): bool
    {
        return $this->visits()
            ->whereIn('status', [SiteVisitStatus::Confirmed, SiteVisitStatus::Completed])
            ->exists();
    }

    public function scopeVisibleToStaff(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('staff.buyers') || $user->hasPermission('staff.sourcing')) {
            return $query;
        }

        return $query->where('assigned_to', $user->id);
    }
}
