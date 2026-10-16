<?php

namespace App\Models;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\PartnerCapability;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array{legal_name?: string, applicant_type?: string, earb_registration_number?: ?string, certificate_expires_on?: ?string, evidence?: array<string, array{disk: string, path: string, mime: ?string, sha256?: string}>, identity?: array<string, mixed>, licence?: array<string, mixed>}|null $verification
 * @property list<array<string, mixed>>|null $verification_history
 */
#[Fillable([
    'user_id',
    'capability',
    'status',
    'organization',
    'statement',
    'decided_by',
    'decided_at',
    'decision_reason',
])]
class CapabilityApplication extends Model
{
    protected $hidden = ['verification', 'verification_history'];

    protected function casts(): array
    {
        return [
            'capability' => PartnerCapability::class,
            'status' => CapabilityApplicationStatus::class,
            'decided_at' => 'datetime',
            'verification' => 'encrypted:array',
            'verification_history' => 'encrypted:array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
