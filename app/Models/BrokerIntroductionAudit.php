<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrokerIntroductionAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'broker_introduction_id',
        'actor_id',
        'action',
        'from_status',
        'to_status',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function introduction(): BelongsTo
    {
        return $this->belongsTo(BrokerIntroduction::class, 'broker_introduction_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
