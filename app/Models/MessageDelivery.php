<?php

namespace App\Models;

use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'idempotency_key',
    'user_id',
    'event',
    'channel',
    'status',
    'attempts',
    'subject',
    'body',
    'destination',
    'error',
    'sent_at',
])]
class MessageDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'event' => MessageEvent::class,
            'channel' => MessageChannel::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
