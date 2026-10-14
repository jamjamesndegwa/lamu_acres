<?php

namespace App\Models;

use App\Enums\MessageEvent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event', 'subject', 'body'])]
class MessageTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'event' => MessageEvent::class,
        ];
    }
}
