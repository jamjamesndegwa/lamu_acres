<?php

namespace Database\Factories;

use App\Enums\PartnerCapability;
use App\Models\CapabilityApplication;
use App\Models\Entitlement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withRoles(string ...$roles): static
    {
        return $this->afterCreating(function (User $user) use ($roles): void {
            $user->syncRoles($roles);
        });
    }

    /** Explicit test-only current credentials; role fixtures alone do not verify a capability. */
    public function withVerifiedCapability(string $capability): static
    {
        return $this->afterCreating(function (User $user) use ($capability): void {
            $reviewer = User::factory()->create();
            $reviewer->assignRole('staff');
            $reviewer->givePermissionTo('staff.brokerage');
            $check = [
                'outcome' => 'verified', 'reviewed_by' => $reviewer->id, 'verified_at' => now()->toIso8601String(),
                'reference' => 'TEST ONLY: reviewed fixture', 'next_review_on' => now()->addYear()->toDateString(),
                'authoritative_checked' => true, 'contact_fingerprint' => hash('sha256', $user->email),
            ];
            $broker = $capability === PartnerCapability::Broker->value;
            $evidence = ['identity' => ['disk' => 'local', 'path' => 'partner-evidence/'.$user->id.'/fixture-id.pdf', 'mime' => 'application/pdf']];
            if ($broker) {
                $evidence['practising_certificate'] = ['disk' => 'local', 'path' => 'partner-evidence/'.$user->id.'/fixture-certificate.pdf', 'mime' => 'application/pdf'];
            }
            $application = CapabilityApplication::query()->firstOrNew(['user_id' => $user->id, 'capability' => $capability]);
            $application->forceFill([
                'status' => 'approved', 'statement' => 'Test-only approved capability fixture.', 'decided_by' => $reviewer->id, 'decided_at' => now(),
                'verification' => [
                    'legal_name' => $user->name, 'applicant_type' => $broker ? 'individual' : 'individual_owner',
                    'earb_registration_number' => $broker ? 'TEST-EARB-0001' : null,
                    'certificate_expires_on' => $broker ? now()->addYear()->toDateString() : null,
                    'evidence' => $evidence, 'identity' => $check, 'licence' => $check,
                ],
            ])->save();
        });
    }

    public function withPermissions(string ...$permissions): static
    {
        return $this->afterCreating(function (User $user) use ($permissions): void {
            $user->givePermissionTo($permissions);
        });
    }

    public function withInvestorPass(mixed $endsAt = null): static
    {
        return $this->afterCreating(function (User $user) use ($endsAt): void {
            $user->entitlements()->create([
                'code' => Entitlement::INVESTOR_PASS,
                'status' => Entitlement::STATUS_ACTIVE,
                'starts_at' => now()->subMinute(),
                'ends_at' => $endsAt,
            ]);
        });
    }
}
