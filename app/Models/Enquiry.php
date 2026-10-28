<?php

namespace App\Models;

use App\Enums\EnquiryStatus;
use App\Enums\LeadStatus;
use Database\Factories\EnquiryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'buyer_id',
    'property_id',
    'assigned_to',
    'broker_introduction_id',
    'status',
    'lead_status',
    'message',
    'referrer_url',
    'campaign',
    'source',
    'ip_address',
    'user_agent',
    'flagged_as_spam',
    'purchase_request',
    'requested_price',
    'requested_acreage',
    'sale_mode_at_request',
    'responded_at',
    'closed_at',
])]
class Enquiry extends Model
{
    /** @use HasFactory<EnquiryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => EnquiryStatus::class,
            'lead_status' => LeadStatus::class,
            'flagged_as_spam' => 'boolean',
            'purchase_request' => 'boolean',
            'requested_price' => 'integer',
            'requested_acreage' => 'decimal:4',
            'responded_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function brokerIntroduction(): BelongsTo
    {
        return $this->belongsTo(BrokerIntroduction::class);
    }

    public function salesTransaction(): HasOne
    {
        return $this->hasOne(SalesTransaction::class);
    }

    public function responses(): HasMany
    {
        return $this->hasMany(EnquiryResponse::class)->latest();
    }

    public function buyerVisibleResponses(): HasMany
    {
        return $this->responses()->where('visible_to_buyer', true);
    }

    public function scopeVisibleToStaff(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('staff.buyers')) {
            return $query;
        }

        return $query->where('assigned_to', $user->id);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->buyer_id === $user->id;
    }

    public function buyerCanWithdrawPurchaseRequest(): bool
    {
        if (! $this->purchase_request || $this->closed_at !== null) {
            return false;
        }

        if ($this->status === EnquiryStatus::Closed || $this->lead_status === LeadStatus::ClosedLost) {
            return false;
        }

        $transaction = $this->salesTransaction;

        if ($transaction === null || ! $transaction->isOpen()) {
            return false;
        }

        return $transaction->outcome === null || ! $transaction->outcome->terminal();
    }
}
