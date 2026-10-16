<?php

namespace Tests\Feature\Verification;

use App\Enums\AuthorityScope;
use App\Enums\AuthorityStatus;
use App\Enums\ListingPurpose;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\PriceBasis;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\User;
use App\Services\Broker\PropertySubmission;
use App\Services\Seller\SellerPortal;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Foundation\FoundationTestCase;

class PublicationGateTest extends FoundationTestCase
{
    public function test_workspace_reports_ready_only_when_publication_gates_are_clear(): void
    {
        [$staff, $seller, $property] = $this->createEligibleProperty();
        $this->clearCriticalVerification($property, $staff);

        $response = $this->actingAs($staff)->get(route('staff.properties.show', $property));

        $response->assertOk()->assertSee('Publication checks are clear.')
            ->assertSee('Confirm that your document review is complete')
            ->assertDontSee('Publication is blocked.');
        $this->assertSame([], $response->viewData('blockers'));
        $this->assertSame(PropertyStatus::Review, $property->fresh()->status);
    }

    public function test_all_publication_paths_blocked_when_unverified(): void
    {
        [$staff, $seller, $property] = $this->createEligibleProperty();

        // Deliberately do not clear verification items (leave in default unverified/not_supplied state)
        // Reset passport/verification items so it remains unverified
        $property->verificationItems()->update(['state' => 'not_supplied']);

        // Path 1: Staff transition route
        $this->actingAs($staff)
            ->put(route('staff.properties.transition', $property), ['status' => PropertyStatus::Published->value])
            ->assertSessionHasErrors('listing');
        $this->assertNotSame(PropertyStatus::Published, $property->refresh()->status);

        // Path 2: Broker/staff submission approve
        $submission = app(PropertySubmission::class);
        try {
            $submission->approve($staff, $property);
            $this->fail('Expected ValidationException when approving unverified submission.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('listing', $e->errors());
        }
        $this->assertNotSame(PropertyStatus::Published, $property->refresh()->status);

        // Path 3: Staff listing review approve route
        $this->actingAs($staff)
            ->post(route('staff.properties.listing', $property))
            ->assertSessionHasErrors('listing');
        $this->assertNotSame(PropertyStatus::Published, $property->refresh()->status);
    }

    public function test_publication_blocked_when_marketing_authority_is_missing_or_wrong_scope(): void
    {
        [$staff, $seller, $property] = $this->createEligibleProperty();
        $this->clearCriticalVerification($property, $staff);

        // 1. Delete marketing authority
        $property->authorities()->delete();
        $property->unsetRelation('authorities');

        $this->actingAs($staff)
            ->post(route('staff.properties.listing', $property))
            ->assertSessionHasErrors('listing');
        $this->assertNotSame(PropertyStatus::Published, $property->refresh()->status);

        // 2. Marketing authority with wrong scope (e.g. lease scope on a sale listing)
        $property->authorities()->create([
            'seller_id' => $seller->id,
            'scope' => AuthorityScope::Lease,
            'status' => AuthorityStatus::Active,
            'terms' => 'Authorised for lease only.',
        ]);
        $property->unsetRelation('authorities');

        $this->actingAs($staff)
            ->post(route('staff.properties.listing', $property))
            ->assertSessionHasErrors('listing');
        $this->assertNotSame(PropertyStatus::Published, $property->refresh()->status);
    }

    public function test_publication_blocked_when_no_public_photo_exists(): void
    {
        [$staff, $seller, $property] = $this->createEligibleProperty();
        $this->clearCriticalVerification($property, $staff);

        // Delete media
        $property->media()->delete();
        $property->unsetRelation('media');

        $this->actingAs($staff)
            ->post(route('staff.properties.listing', $property))
            ->assertSessionHasErrors('listing');
        $this->assertNotSame(PropertyStatus::Published, $property->refresh()->status);
    }

    public function test_publication_blocked_when_duplicate_parcel_exists(): void
    {
        [$staff, $seller, $property] = $this->createEligibleProperty();
        $this->clearCriticalVerification($property, $staff);

        // Create another property already published with the same parcel identifier
        Property::factory()->published()->create([
            'parcel_identifier' => $property->parcel_identifier,
        ]);

        $this->actingAs($staff)
            ->post(route('staff.properties.listing', $property))
            ->assertSessionHasErrors('listing');
        $this->assertNotSame(PropertyStatus::Published, $property->refresh()->status);
    }

    public function test_publication_succeeds_through_all_three_paths_when_eligible(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();

        // Test Path 1: Staff transition
        [$staff, $seller, $property1] = $this->createEligibleProperty($staff);
        $this->clearCriticalVerification($property1, $staff);

        $this->actingAs($staff)
            ->put(route('staff.properties.transition', $property1), ['status' => PropertyStatus::Published->value])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame(PropertyStatus::Published, $property1->refresh()->status);
        $this->assertSame(PropertyAvailability::Available, $property1->availability);

        // Test Path 2: PropertySubmission::approve
        [$staff, $seller, $property2] = $this->createEligibleProperty($staff);
        $this->clearCriticalVerification($property2, $staff);

        $submission = app(PropertySubmission::class);
        $approved = $submission->approve($staff, $property2);
        $this->assertSame(PropertyStatus::Published, $approved->status);
        $this->assertSame(PropertyAvailability::Available, $approved->availability);

        // Test Path 3: SellerDeskController::approveListing
        [$staff, $seller, $property3] = $this->createEligibleProperty($staff);
        $this->clearCriticalVerification($property3, $staff);

        $this->actingAs($staff)
            ->post(route('staff.properties.listing', $property3))
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame(PropertyStatus::Published, $property3->refresh()->status);
        $this->assertSame(PropertyAvailability::Available, $property3->availability);
    }

    /**
     * @return array{0: User, 1: User, 2: Property}
     */
    private function createEligibleProperty(?User $staff = null): array
    {
        $staff ??= User::factory()->withRoles('staff')->withPermissions('staff.properties', 'staff.verification')->create();
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();

        $county = County::query()->firstOrCreate(['slug' => 'lamu'], ['name' => 'Lamu', 'is_active' => true]);
        $locality = Locality::query()->firstOrCreate(
            ['county_id' => $county->id, 'slug' => 'shela'],
            ['name' => 'Shela', 'kind' => 'place', 'is_active' => true]
        );

        $property = Property::factory()->listedIn($locality)->create([
            'title' => 'Eligible Shela Holding',
            'summary' => 'A prime parcel ready for sale.',
            'description' => 'Detailed description of the parcel.',
            'purpose' => ListingPurpose::Sale,
            'price_basis' => PriceBasis::Total,
            'price_amount' => 5_000_000,
            'acreage' => 3.0,
            'parcel_identifier' => 'LAMU/SHELA/'.fake()->unique()->numberBetween(100, 999),
            'status' => PropertyStatus::Review,
            'availability' => PropertyAvailability::Unavailable,
            'submitted_by' => $seller->id,
        ]);

        $property->owners()->create([
            'user_id' => $seller->id,
            'relationship' => OwnerRelationship::Owner,
            'status' => OwnerLinkStatus::Verified,
        ]);

        $property->authorities()->create([
            'seller_id' => $seller->id,
            'scope' => AuthorityScope::Sale,
            'status' => AuthorityStatus::Active,
            'terms' => 'Authorised to market this property for sale.',
        ]);

        app(SellerPortal::class)->verifyLink($property->owners()->firstOrFail(), $staff, null, 'Test review: identity and authority match.', true);
        $this->attachPublicPhoto($property, $seller);

        return [$staff, $seller, $property];
    }
}
