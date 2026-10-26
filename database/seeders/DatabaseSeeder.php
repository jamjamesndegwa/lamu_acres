<?php

namespace Database\Seeders;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\PartnerCapability;
use App\Models\CapabilityApplication;
use App\Models\Entitlement;
use App\Models\User;
use App\Services\Access\RoleAssigner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AccessSeeder::class,
            GeographySeeder::class,
            SystemDefaultsSeeder::class,
            InitialAdministratorSeeder::class,
        ]);

        if (! app()->environment('local')) {
            return;
        }

        $accounts = [
            ['name' => 'Buyer Account', 'email' => 'buyer@lamuacres.test', 'roles' => ['buyer']],
            ['name' => 'Broker Account', 'email' => 'broker@lamuacres.test', 'roles' => ['broker']],
            ['name' => 'Landowner Account', 'email' => 'seller@lamuacres.test', 'roles' => ['seller']],
            ['name' => 'Investor Account', 'email' => 'investor@lamuacres.test', 'roles' => ['buyer'], 'investor_pass' => true],
            ['name' => 'Staff Account', 'email' => 'staff@lamuacres.test', 'roles' => ['staff'], 'permissions' => [
                'staff.overview',
                'staff.properties',
                'staff.buyers',
                'staff.sourcing',
                'staff.brokerage',
                'staff.sales',
                'staff.verification',
                'staff.intelligence',
                'staff.finance',
                'staff.communications',
            ]],
            ['name' => 'Verification Officer', 'email' => 'verification@lamuacres.test', 'roles' => ['staff'], 'permissions' => ['staff.verification']],
            ['name' => 'Finance Officer', 'email' => 'finance@lamuacres.test', 'roles' => ['staff'], 'permissions' => ['staff.finance', 'commissions.propose', 'commissions.approve', 'commissions.record']],
            ['name' => 'Administrator', 'email' => 'administrator@lamuacres.test', 'roles' => ['admin']],
            ['name' => 'Super Administrator', 'email' => 'admin@lamuacres.test', 'roles' => ['super_admin']],
            ['name' => 'Multi Workspace', 'email' => 'multi@lamuacres.test', 'roles' => ['buyer', 'broker']],
        ];

        $roles = app(RoleAssigner::class);
        $reviewer = null;

        foreach ($accounts as $account) {
            $user = User::query()->updateOrCreate(
                ['email' => $account['email']],
                [
                    'name' => $account['name'],
                    'password' => 'password',
                    'email_verified_at' => now(),
                ],
            );

            $capabilityRoles = array_values(array_filter(
                $account['roles'],
                fn (string $role): bool => PartnerCapability::tryFrom($role) !== null,
            ));
            $otherRoles = array_values(array_diff($account['roles'], $capabilityRoles));

            if ($otherRoles !== []) {
                $roles->sync($user, $otherRoles);
            }

            if ($capabilityRoles !== []) {
                $reviewer ??= $this->localCapabilityReviewer();

                foreach ($capabilityRoles as $capability) {
                    $this->seedVerifiedCapability($user, PartnerCapability::from($capability), $reviewer);
                }

                $roles->sync($user, $account['roles']);
            }

            if ($account['permissions'] ?? []) {
                $user->syncPermissions($account['permissions']);
            }

            if ($account['investor_pass'] ?? false) {
                $user->entitlements()->updateOrCreate(
                    ['code' => Entitlement::INVESTOR_PASS],
                    [
                        'status' => Entitlement::STATUS_ACTIVE,
                        'starts_at' => now(),
                        'ends_at' => null,
                    ],
                );
            }
        }
    }

    private function localCapabilityReviewer(): User
    {
        $reviewer = User::query()->updateOrCreate(
            ['email' => 'staff@lamuacres.test'],
            [
                'name' => 'Staff Account',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );

        $reviewer->syncRoles(['staff']);
        $reviewer->syncPermissions([
            'staff.overview',
            'staff.properties',
            'staff.buyers',
            'staff.sourcing',
            'staff.brokerage',
            'staff.sales',
            'staff.verification',
            'staff.intelligence',
            'staff.finance',
            'staff.communications',
        ]);

        return $reviewer;
    }

    private function seedVerifiedCapability(User $user, PartnerCapability $capability, User $reviewer): void
    {
        $broker = $capability === PartnerCapability::Broker;
        $directory = 'partner-evidence/'.$user->id;
        $identityPath = $directory.'/local-identity.pdf';
        $certificatePath = $directory.'/local-certificate.pdf';

        Storage::disk('local')->put($identityPath, '%PDF-1.4 local identity fixture');
        if ($broker) {
            Storage::disk('local')->put($certificatePath, '%PDF-1.4 local certificate fixture');
        }

        $check = [
            'outcome' => 'verified',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now()->toIso8601String(),
            'verified_at' => now()->toIso8601String(),
            'reference' => 'LOCAL SEED: verified partner credentials',
            'next_review_on' => now()->addYear()->toDateString(),
            'authoritative_checked' => true,
            'contact_fingerprint' => hash('sha256', (string) $user->email),
        ];

        $evidence = [
            'identity' => [
                'disk' => 'local',
                'path' => $identityPath,
                'mime' => 'application/pdf',
                'sha256' => hash('sha256', '%PDF-1.4 local identity fixture'),
            ],
        ];

        if ($broker) {
            $evidence['practising_certificate'] = [
                'disk' => 'local',
                'path' => $certificatePath,
                'mime' => 'application/pdf',
                'sha256' => hash('sha256', '%PDF-1.4 local certificate fixture'),
            ];
        }

        CapabilityApplication::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'capability' => $capability,
            ],
            [
                'organization' => null,
                'statement' => 'Local development seed for a verified '.$capability->label().' account.',
                'status' => CapabilityApplicationStatus::Approved,
                'decision_reason' => 'Local development seed.',
                'decided_by' => $reviewer->id,
                'decided_at' => now(),
                'verification' => [
                    'legal_name' => $user->name,
                    'applicant_type' => $broker ? 'individual' : 'individual_owner',
                    'earb_registration_number' => $broker ? 'LOCAL-EARB-0001' : null,
                    'certificate_expires_on' => $broker ? now()->addYear()->toDateString() : null,
                    'evidence' => $evidence,
                    'identity' => $check,
                    'licence' => $check,
                ],
                'verification_history' => [[
                    'event' => 'local_seed',
                    'actor_id' => $reviewer->id,
                    'at' => now()->toIso8601String(),
                    'to' => CapabilityApplicationStatus::Approved->value,
                ]],
            ],
        );
    }
}
