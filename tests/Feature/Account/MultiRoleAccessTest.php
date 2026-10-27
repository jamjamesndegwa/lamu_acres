<?php

namespace Tests\Feature\Account;

use App\Enums\CapabilityApplicationStatus;
use App\Enums\PartnerCapability;
use App\Models\Property;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class MultiRoleAccessTest extends FoundationTestCase
{
    public function test_seller_without_buyer_role_cannot_open_buyer_workspace(): void
    {
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $seller->syncRoles(['seller']);

        $this->actingAs($seller)->get('/seller')->assertOk();
        $this->actingAs($seller)->get('/buyer')->assertForbidden();
    }

    public function test_buyer_without_seller_approval_cannot_open_seller_workspace(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->get('/buyer')->assertOk();
        $this->actingAs($buyer)->get('/seller')->assertForbidden();
    }

    public function test_cross_role_applications_do_not_grant_workspace_until_approved(): void
    {
        Storage::fake('local');
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->post(route('account.applications.store'), [
            'capability' => 'seller',
            'statement' => 'I hold land at Mokowe.',
            'legal_name' => 'Applicant', 'applicant_type' => 'individual_owner',
            'identity' => UploadedFile::fake()->create('identity.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertFalse($buyer->fresh()->hasRole('seller'));
        $this->actingAs($buyer)->get('/seller')->assertForbidden();

        $application = $buyer->capabilityApplications()->firstOrFail();
        $application->forceFill(['status' => CapabilityApplicationStatus::Rejected, 'decision_reason' => 'No'])->save();

        $this->actingAs($buyer)->get('/seller')->assertForbidden();
    }

    public function test_verified_broker_opens_buyer_and_seller_workspaces_without_a_separate_seller_application(): void
    {
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $broker->syncRoles(['broker']);

        $this->actingAs($broker)->get('/buyer')->assertOk();
        $this->actingAs($broker)->get('/seller')->assertOk();
        $this->actingAs($broker)->get('/broker')->assertOk();
    }

    public function test_unverified_broker_cannot_post_restricted_broker_actions(): void
    {
        $broker = User::factory()->withRoles('broker')->create();

        $this->actingAs($broker)->get('/broker')->assertOk();
        $this->actingAs($broker)->post(route('broker.listings.store'), [
            'title' => 'Should not save',
        ])->assertSessionHasErrors('capability');
    }

    public function test_broker_cannot_manage_another_users_listing(): void
    {
        $owner = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $other = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $property = Property::factory()->create(['submitted_by' => $owner->id, 'title' => 'Owned plot']);

        $this->actingAs($other)->get(route('broker.listings.show', $property))->assertNotFound();
    }

    public function test_partner_roles_cannot_open_investor_workspace_without_entitlement(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();

        $this->actingAs($buyer)->get('/investor')->assertForbidden();
        $this->actingAs($seller)->get('/investor')->assertForbidden();
        $this->actingAs($broker)->get('/investor')->assertForbidden();
    }

    public function test_expired_investor_entitlement_removes_investor_workspace_only(): void
    {
        $buyer = User::factory()->withRoles('buyer')->withInvestorPass(now()->subDay())->create();

        $this->actingAs($buyer)->get('/investor')->assertForbidden();
        $this->actingAs($buyer)->get('/buyer')->assertOk();
    }

    public function test_broker_workspace_navigation_exposes_unified_buyer_and_broker_modules(): void
    {
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();

        $this->actingAs($broker)
            ->get('/broker')
            ->assertOk()
            ->assertSee('Find Land')
            ->assertSee('My Enquiries')
            ->assertSee('My Listings')
            ->assertSee('My Deals');
    }

    public function test_seller_can_apply_for_broker_capability_without_a_new_account(): void
    {
        Storage::fake('local');
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $seller->syncRoles(['seller']);

        $this->actingAs($seller)->post(route('account.applications.store'), [
            'capability' => 'broker',
            'organization' => 'Coast Office',
            'statement' => 'I introduce buyers around Shela.',
            'legal_name' => 'Seller Broker Applicant', 'applicant_type' => 'individual',
            'identity' => UploadedFile::fake()->create('identity.pdf', 10, 'application/pdf'),
            'earb_registration_number' => 'TEST-EARB-456',
            'certificate_expires_on' => now()->addYear()->toDateString(),
            'practising_certificate' => UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf'),
        ])->assertRedirect(route('account.applications.index'));

        $this->assertFalse($seller->fresh()->hasRole('broker'));
        $this->assertSame(
            1,
            $seller->capabilityApplications()->where('capability', PartnerCapability::Broker)->count(),
        );
    }
}
