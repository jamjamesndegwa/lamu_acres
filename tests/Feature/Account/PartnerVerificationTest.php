<?php

namespace Tests\Feature\Account;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\PartnerCapability;
use App\Models\Property;
use App\Models\User;
use App\Services\Access\CapabilityReviewer;
use App\Services\Access\RoleAssigner;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Foundation\FoundationTestCase;

class PartnerVerificationTest extends FoundationTestCase
{
    public function test_seller_requires_identity_review_without_requiring_earb_credentials(): void
    {
        Notification::fake();
        Storage::fake('local');
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = $this->reviewer();
        $this->actingAs($buyer)->post(route('account.applications.store'), $this->payload('seller'))
            ->assertRedirect(route('account.applications.index'));
        $application = $buyer->capabilityApplications()->firstOrFail();
        $this->assertFalse($buyer->fresh()->hasRole('seller'));
        $this->assertNull($application->verification['earb_registration_number']);
        $this->assertSame('pending', $application->verification['identity']['outcome']);
        $this->actingAs($staff)->put(route('staff.applications.update', $application), [
            'status' => 'approved', 'decision_reason' => 'Ownership statement alone is not verification.',
        ])->assertSessionHasErrors('status');
        $this->assertSame(CapabilityApplicationStatus::Pending, $application->fresh()->status);

        $this->actingAs($staff)->put(route('staff.applications.update', $application), $this->decision(false))
            ->assertSessionHasNoErrors()->assertRedirect();
        $application->refresh();
        $this->assertTrue($buyer->fresh()->hasRole('seller'));
        $this->assertSame($staff->id, $application->verification['identity']['reviewed_by']);
        $this->assertSame('approved', $application->status->value);
        $this->assertCount(2, $application->verification_history);
        $this->assertArrayNotHasKey('verification', $application->toArray());
    }

    public function test_broker_cannot_self_assign_or_be_approved_without_authoritative_professional_review(): void
    {
        Notification::fake();
        Storage::fake('local');
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = $this->reviewer();
        $payload = $this->payload('broker');
        $payload['role'] = 'broker';
        $payload['identity_outcome'] = 'verified';
        $payload['licence_outcome'] = 'verified';
        $payload['earb_checked'] = 1;
        $this->actingAs($buyer)->post(route('account.applications.store'), $payload)->assertSessionHasNoErrors();
        $application = $buyer->capabilityApplications()->firstOrFail();
        $this->assertFalse($buyer->fresh()->hasRole('broker'));
        $this->assertSame('pending', $application->verification['licence']['outcome']);
        $this->actingAs($buyer)->put(route('staff.applications.update', $application), $this->decision(true))->assertForbidden();

        $withoutEarb = $this->decision(true);
        unset($withoutEarb['earb_checked']);
        $this->actingAs($staff)->put(route('staff.applications.update', $application), $withoutEarb)->assertSessionHasErrors('earb_checked');
        $this->assertSame(CapabilityApplicationStatus::Pending, $application->fresh()->status);
        $this->actingAs($staff)->put(route('staff.applications.update', $application), $this->decision(true))->assertSessionHasNoErrors();
        $this->assertTrue($buyer->fresh()->hasRole('broker'));
        $this->assertSame($staff->id, $application->fresh()->verification['licence']['reviewed_by']);
    }

    public function test_broker_expiry_blocks_new_actions_but_preserves_approved_status_and_role_history(): void
    {
        $this->freezeTime();
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $application = $broker->capabilityApplications()->firstOrFail();
        $profile = $application->verification;
        $profile['certificate_expires_on'] = yesterday()->toDateString();
        $application->forceFill(['verification' => $profile])->save();

        $this->actingAs($broker)->post(route('broker.listings.store'), [])->assertSessionHasErrors('capability');
        $this->actingAs($broker)->post(route('broker.referrals.store'), [])->assertSessionHasErrors('capability');
        $this->actingAs($broker)->get(route('broker.agreements.index'))->assertOk();
        $this->assertTrue($broker->fresh()->hasRole('broker'));
        $this->assertSame(CapabilityApplicationStatus::Approved, $application->fresh()->status);
        $this->assertSame(0, Property::query()->where('submitted_by', $broker->id)->count());
    }

    public function test_broker_needs_verified_contact_and_valid_certificate_and_firms_need_business_evidence(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->unverified()->withRoles('buyer')->create();
        $staff = $this->reviewer();
        $missingCertificate = $this->payload('broker');
        unset($missingCertificate['practising_certificate']);
        $this->actingAs($buyer)->post(route('account.applications.store'), $missingCertificate)->assertSessionHasErrors('practising_certificate');
        $expired = $this->payload('broker');
        $expired['certificate_expires_on'] = yesterday()->toDateString();
        $this->actingAs($buyer)->post(route('account.applications.store'), $expired)->assertSessionHasErrors('certificate_expires_on');
        $firm = $this->payload('broker');
        $firm['applicant_type'] = 'firm';
        $this->actingAs($buyer)->post(route('account.applications.store'), $firm)->assertSessionHasErrors(['organization', 'business_document']);
        $this->actingAs($buyer)->post(route('account.applications.store'), $this->payload('broker'))->assertSessionHasNoErrors();
        $application = $buyer->capabilityApplications()->firstOrFail();
        $this->actingAs($staff)->put(route('staff.applications.update', $application), $this->decision(true))->assertSessionHasErrors('status');
        $this->assertFalse($buyer->fresh()->hasRole('broker'));
    }

    public function test_identity_evidence_is_private_and_restricted_to_the_applicant_and_review_desk(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->withRoles('buyer')->create();
        $stranger = User::factory()->withRoles('buyer')->create();
        $plainStaff = User::factory()->withRoles('staff')->create();
        $staff = $this->reviewer();
        $this->actingAs($buyer)->post(route('account.applications.store'), $this->payload('seller'));
        $application = $buyer->capabilityApplications()->firstOrFail();
        $file = $application->verification['evidence']['identity'];
        Storage::disk('local')->assertExists($file['path']);
        $url = route('account.applications.evidence', [$application, 'identity']);
        $this->actingAs($stranger)->get($url)->assertForbidden();
        $this->actingAs($plainStaff)->get($url)->assertForbidden();
        $this->actingAs($staff)->get($url)->assertOk()->assertDownload('identity.pdf');
        $preview = route('account.applications.evidence', [$application, 'identity', 'preview' => 1]);
        $this->actingAs($stranger)->get($preview)->assertForbidden();
        $this->actingAs($plainStaff)->get($preview)->assertForbidden();
        $this->actingAs($staff)->get($preview)->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Content-Disposition', 'inline; filename=identity.pdf');
        $this->actingAs($buyer)->get($url)->assertOk();
        $this->actingAs($staff)->get(route('account.applications.evidence', [$application, 'unsupported']))->assertNotFound();
        $this->assertArrayNotHasKey('verification', $application->toArray());
    }

    public function test_staff_can_review_a_seller_application_and_its_documents_on_a_full_page(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = $this->reviewer();
        $this->actingAs($buyer)->post(route('account.applications.store'), $this->payload('seller'));
        $application = $buyer->capabilityApplications()->firstOrFail();

        $this->actingAs($staff)->get(route('staff.applications.index'))
            ->assertOk()->assertSee('Review application');
        $this->actingAs($staff)->get(route('staff.applications.edit', $application))
            ->assertOk()
            ->assertSee('Back to applications')
            ->assertSee('Uploaded documents')
            ->assertSee('title="Identity preview"', false)
            ->assertSee('Record staff verification')
            ->assertSee('Record decision')
            ->assertSee('Review history')
            ->assertDontSee('role="dialog"', false);
    }

    public function test_suspension_requires_staff_reopening_and_reapplication_resets_checks(): void
    {
        Notification::fake();
        Storage::fake('local');
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = $this->reviewer();
        $this->actingAs($buyer)->post(route('account.applications.store'), $this->payload('seller'));
        $application = $buyer->capabilityApplications()->firstOrFail();
        $this->actingAs($staff)->put(route('staff.applications.update', $application), $this->decision(false))->assertSessionHasNoErrors();
        $this->actingAs($staff)->put(route('staff.applications.update', $application), ['status' => 'suspended', 'decision_reason' => 'Identity concerns need further review.'])->assertSessionHasNoErrors();
        $this->assertFalse($buyer->fresh()->hasRole('seller'));
        $this->actingAs($buyer)->post(route('account.applications.store'), $this->payload('seller'))->assertSessionHasErrors('capability');
        $this->actingAs($staff)->put(route('staff.applications.update', $application), ['status' => 'information_required', 'decision_reason' => 'Please provide current identity evidence.'])->assertSessionHasNoErrors();
        $this->actingAs($buyer)->post(route('account.applications.store'), $this->payload('seller'))->assertSessionHasNoErrors();
        $this->assertSame('pending', $application->fresh()->verification['identity']['outcome']);
        $this->assertFalse($buyer->fresh()->hasRole('seller'));
        $this->assertGreaterThan(3, count($application->fresh()->verification_history));
    }

    public function test_broker_renewal_resets_verification_until_staff_review_current_evidence(): void
    {
        Notification::fake();
        Storage::fake('local');
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $staff = $this->reviewer();
        $application = $broker->capabilityApplications()->firstOrFail();

        $this->actingAs($broker)->post(route('account.applications.store'), $this->payload('broker'))->assertSessionHasNoErrors();
        $this->assertSame(CapabilityApplicationStatus::Pending, $application->fresh()->status);
        $this->assertSame('pending', $application->fresh()->verification['licence']['outcome']);
        $this->actingAs($broker)->post(route('broker.referrals.store'), [])->assertSessionHasErrors('capability');
        $this->actingAs($broker)->get(route('broker.agreements.index'))->assertOk();
        $this->actingAs($staff)->put(route('staff.applications.update', $application), $this->decision(true))->assertSessionHasNoErrors();
        $this->assertTrue(app(CapabilityReviewer::class)->hasCurrentCapability($broker->fresh(), PartnerCapability::Broker));
    }

    public function test_onboarding_files_use_a_separate_private_bucket_and_never_the_public_bucket(): void
    {
        Storage::fake('local');
        Storage::fake('r2_private');
        Storage::fake('r2');
        config(['filesystems.disks.r2.bucket' => 'public-listings', 'filesystems.disks.r2_private.bucket' => 'private-identities']);
        $applicant = User::factory()->withRoles('buyer')->create();

        $this->actingAs($applicant)->post(route('account.applications.store'), $this->payload('seller'))->assertSessionHasNoErrors();
        $evidence = $applicant->capabilityApplications()->firstOrFail()->verification['evidence']['identity'];
        $this->assertSame('r2_private', $evidence['disk']);
        Storage::disk('r2_private')->assertExists($evidence['path']);
        $this->assertSame([], Storage::disk('r2')->allFiles());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_shared_bucket_configuration_falls_back_to_local_private_storage(): void
    {
        Storage::fake('local');
        Storage::fake('r2_private');
        config(['filesystems.disks.r2.bucket' => 'shared-bucket', 'filesystems.disks.r2_private.bucket' => 'shared-bucket']);
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->post(route('account.applications.store'), $this->payload('seller'))->assertSessionHasNoErrors();

        $evidence = $buyer->capabilityApplications()->firstOrFail()->verification['evidence']['identity'];
        $this->assertSame('local', $evidence['disk']);
        Storage::disk('local')->assertExists($evidence['path']);
        $this->assertSame([], Storage::disk('r2_private')->allFiles());
    }

    public function test_representative_evidence_is_required_and_missing_files_cannot_be_approved(): void
    {
        Notification::fake();
        Storage::fake('local');
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = $this->reviewer();
        $payload = $this->payload('seller');
        $payload['applicant_type'] = 'company_representative';
        $this->actingAs($buyer)->post(route('account.applications.store'), $payload)
            ->assertSessionHasErrors(['organization', 'business_document', 'representative_authority']);
        $this->actingAs($buyer)->post(route('account.applications.store'), $this->payload('seller'))->assertSessionHasNoErrors();
        $application = $buyer->capabilityApplications()->firstOrFail();
        $file = $application->verification['evidence']['identity'];
        Storage::disk('local')->delete($file['path']);

        $this->actingAs($staff)->put(route('staff.applications.update', $application), $this->decision(false))->assertSessionHasErrors('status');

        $this->assertFalse($buyer->fresh()->hasRole('seller'));
        $this->assertSame(CapabilityApplicationStatus::Pending, $application->fresh()->status);
    }

    public function test_role_assignment_service_cannot_bypass_the_application_checks(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $this->expectException(ValidationException::class);

        app(RoleAssigner::class)->assign($buyer, PartnerCapability::Broker->value);
    }

    /** @return array<string, mixed> */
    private function payload(string $capability): array
    {
        $data = [
            'capability' => $capability, 'legal_name' => 'Test Legal Applicant',
            'applicant_type' => $capability === 'broker' ? 'individual' : 'individual_owner',
            'statement' => 'I provide current evidence for the partner capability review.',
            'identity' => UploadedFile::fake()->create('identity.pdf', 10, 'application/pdf'),
        ];
        if ($capability === 'broker') {
            $data['earb_registration_number'] = 'TEST-DECLARED-123';
            $data['certificate_expires_on'] = now()->addYear()->toDateString();
            $data['practising_certificate'] = UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf');
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private function decision(bool $broker): array
    {
        $data = [
            'status' => 'approved', 'decision_reason' => 'Test-only staff review of identity evidence completed.',
            'identity_outcome' => 'verified', 'identity_reference' => 'TEST identity review reference',
            'identity_next_review_on' => now()->addYear()->toDateString(),
        ];
        if ($broker) {
            $data += ['licence_outcome' => 'verified', 'licence_reference' => 'TEST authoritative EARB record reference', 'licence_next_review_on' => now()->addYear()->toDateString(), 'earb_checked' => 1];
        }

        return $data;
    }

    private function reviewer(): User
    {
        return User::factory()->withRoles('staff')->withPermissions('staff.brokerage')->create();
    }
}
