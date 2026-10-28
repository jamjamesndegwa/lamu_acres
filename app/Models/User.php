<?php

namespace App\Models;

use App\Enums\Workspace;
use App\Models\Concerns\StoresFileUrls;
use App\Notifications\QueuedResetPassword;
use App\Services\Access\WorkspaceAccess;
use App\Services\Communications\ExternalMailRegistry;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'notify_by_email', 'marketing_email_consent', 'marketing_sms_consent', 'communications_unsubscribe_token'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    use StoresFileUrls;

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->communications_unsubscribe_token)) {
                $user->communications_unsubscribe_token = Str::random(40);
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'notify_by_email' => 'boolean',
            'marketing_email_consent' => 'boolean',
            'marketing_sms_consent' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function avatarUrl(): ?string
    {
        if (blank($this->avatar_path)) {
            return null;
        }

        if (filled($this->avatar_url) && config('filesystems.disks.r2.url')) {
            return $this->avatar_url;
        }

        return route('account.avatar.show', ['v' => $this->updated_at?->getTimestamp()]);
    }

    public function buyerProfile(): HasOne
    {
        return $this->hasOne(BuyerProfile::class);
    }

    public function sellerProfile(): HasOne
    {
        return $this->hasOne(SellerProfile::class);
    }

    public function capabilityApplications(): HasMany
    {
        return $this->hasMany(CapabilityApplication::class);
    }

    public function hasPermission(string $permission): bool
    {
        return $this->checkPermissionTo($permission);
    }

    public function entitlements(): HasMany
    {
        return $this->hasMany(Entitlement::class);
    }

    public function savedProperties(): HasMany
    {
        return $this->hasMany(SavedProperty::class);
    }

    public function notificationPreferences(): HasMany
    {
        return $this->hasMany(NotificationPreference::class);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class, 'buyer_id');
    }

    public function assignedEnquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class, 'assigned_to');
    }

    public function hasActiveInvestorPass(): bool
    {
        return $this->entitlements()
            ->where('code', Entitlement::INVESTOR_PASS)
            ->where('status', Entitlement::STATUS_ACTIVE)
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
            })
            ->exists();
    }

    public function canAccessWorkspace(Workspace $workspace): bool
    {
        return app(WorkspaceAccess::class)->canAccess($this, $workspace);
    }

    public function canUseBuyerFeatures(): bool
    {
        return app(WorkspaceAccess::class)->canUseBuyerFeatures($this);
    }

    public function canUseSellerFeatures(): bool
    {
        return app(WorkspaceAccess::class)->canUseSellerFeatures($this);
    }

    public function canUseBrokerFeatures(): bool
    {
        return app(WorkspaceAccess::class)->canUseBrokerFeatures($this);
    }

    /**
     * @return array<int, Workspace>
     */
    public function workspaces(): array
    {
        return array_values(array_filter(
            Workspace::cases(),
            fn (Workspace $workspace): bool => $this->canAccessWorkspace($workspace),
        ));
    }

    public function workspaceHomeUrl(): string
    {
        $workspaces = $this->workspaces();

        if (count($workspaces) === 1) {
            return route($workspaces[0]->homeRoute());
        }

        return route('workspaces.index');
    }

    public function sendPasswordResetNotification($token): void
    {
        $notification = new QueuedResetPassword((string) $token);

        app(ExternalMailRegistry::class)->recordQueued(
            $this,
            $notification,
            'Password reset',
            (string) config('communications.queues.critical'),
        );

        $this->notify($notification);
    }
}
