<?php

namespace App\Models;

use App\Enums\MessageCampaignStatus;
use App\Enums\MessageChannel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'created_by',
    'channel',
    'subject',
    'body',
    'status',
    'recipient_count',
    'sent_count',
    'failed_count',
    'queued_at',
    'completed_at',
])]
class MessageCampaign extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => MessageChannel::class,
            'status' => MessageCampaignStatus::class,
            'recipient_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'queued_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(MessageDelivery::class);
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_campaign_recipients')->withTimestamps();
    }
}
