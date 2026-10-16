<?php

namespace App\Models;

use App\Enums\SiteVisitStatus;
use Database\Factories\SiteVisitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'property_match_id',
    'property_visit_request_id',
    'buyer_id',
    'property_id',
    'enquiry_id',
    'assigned_to',
    'status',
    'requested_for',
    'confirmed_for',
    'notes',
    'buyer_acknowledged_at',
    'cancelled_at',
    'completed_at',
])]
class SiteVisit extends Model
{
    /** @use HasFactory<SiteVisitFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => SiteVisitStatus::class,
            'requested_for' => 'datetime',
            'confirmed_for' => 'datetime',
            'buyer_acknowledged_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(PropertyMatch::class, 'property_match_id');
    }

    public function propertyVisitRequest(): BelongsTo
    {
        return $this->belongsTo(PropertyVisitRequest::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->buyer_id === (int) $user->id;
    }

    public function scopeVisibleToStaff(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('staff.buyers')) {
            return $query;
        }

        return $query->where('assigned_to', $user->id);
    }
}
