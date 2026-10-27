<?php

namespace Tests\Feature\Broker;

use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\SaleMode;
use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyInformationRequest;
use App\Models\User;
use App\Notifications\PropertyApprovedNotification;
use App\Notifications\PropertyInformationRequestedNotification;
use App\Notifications\PropertySubmittedNotification;
use App\Services\Broker\DuplicateListingDetector;
use App\Services\Seller\SellerPortal;
use App\Services\Verification\VerificationDesk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class BrokerListingTest extends FoundationTestCase
{
    public function test_a_broker_submission_stays_private_until_staff_approve_it(): void
    {
        Notification::fake();
        Storage::fake('local');

        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $other = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $locality = $this->locality();

        $this->get(route('broker.listings.index'))->assertRedirect(route('login'));
        $this->actingAs($buyer)->get(route('broker.listings.create'))->assertForbidden();

        $this->actingAs($broker)
            ->post(route('broker.listings.store'), $this->payload($locality, [
                'title' => 'First parcel listing',
                'parcel_identifier' => 'LAMU / 88',
                'acreage' => 5,
                'seller_name' => 'Amina Hassan Private',
            ]))
            ->assertRedirect();

        $first = Property::query()->where('title', 'First parcel listing')->firstOrFail();

        $this->assertSame(PropertyStatus::Review, $first->status);
        $this->assertSame(PropertyAvailability::Unavailable, $first->availability);
        $this->assertNull($first->published_at);
        $this->assertSame($broker->id, $first->submitted_by);
        $this->assertSame('LAMU/88', $first->parcel_identifier);
        $this->assertSame(2, $first->media()->count());
        Notification::assertSentTo($staff, PropertySubmittedNotification::class);

        $this->get(route('land.index'))->assertOk()->assertDontSee('First parcel listing');
        $this->get(route('land.show', [$locality->county, $locality, $first]))->assertNotFound();

        $this->actingAs($other)->get(route('broker.listings.index'))->assertOk()->assertDontSee('First parcel listing');
        $this->actingAs($other)->get(route('broker.listings.show', $first))->assertNotFound();

        $this->actingAs($broker)
            ->post(route('broker.listings.store'), $this->payload($locality, [
                'title' => 'Second parcel listing',
                'parcel_identifier' => 'lamu/88',
                'acreage' => 5,
                'seller_name' => 'Second seller private',
            ]))
            ->assertRedirect();

        $second = Property::query()->where('title', 'Second parcel listing')->firstOrFail();

        $this->actingAs($broker)
            ->get(route('broker.listings.show', $second))
            ->assertOk()
            ->assertSee(DuplicateListingDetector::PARCEL_PRIVATE)
            ->assertSee(DuplicateListingDetector::LOCATION)
            ->assertDontSee('First parcel listing')
            ->assertDontSee('Amina Hassan Private');

        $this->actingAs($staff)
            ->get(route('staff.listings.show', $second))
            ->assertOk()
            ->assertSee($first->reference)
            ->assertSee('Second seller private');

        $this->actingAs($staff)
            ->post(route('staff.listings.request', $second), [
                'question' => 'Please confirm the title deed number.',
            ])
            ->assertRedirect();

        $request = PropertyInformationRequest::query()->where('property_id', $second->id)->firstOrFail();
        Notification::assertSentTo($broker, PropertyInformationRequestedNotification::class);

        $this->actingAs($broker)
            ->post(route('broker.listings.respond', [$second, $request]), [
                'response' => 'The deed is ready for inspection.',
            ])
            ->assertRedirect();

        $this->assertSame('The deed is ready for inspection.', $request->fresh()->response);

        $this->clearCriticalChecks($second, $staff);
        $this->actingAs($staff)->post(route('staff.listings.approve', $second))->assertRedirect();

        $second->refresh();
        $this->assertSame(PropertyStatus::Published, $second->status);
        $this->assertSame(PropertyAvailability::Available, $second->availability);
        $this->assertTrue($second->isPubliclyListed());
        Notification::assertSentTo($broker, PropertyApprovedNotification::class);

        $this->get(route('land.show', [$locality->county, $locality, $second]))
            ->assertOk()
            ->assertSee('Second parcel listing')
            ->assertDontSee('Second seller private');

        $this->actingAs($broker)
            ->get(route('broker.listings.index', ['approved' => 1]))
            ->assertOk()
            ->assertSee('Second parcel listing')
            ->assertDontSee('First parcel listing');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Locality $locality, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Broker holding',
            'summary' => 'A coastal holding submitted by the broker.',
            'description' => 'Further description for staff.',
            'locality_id' => $locality->id,
            'acreage' => 4,
            'sale_mode' => SaleMode::Whole->value,
            'price_amount' => 800_000,
            'purpose' => 'sale',
            'property_type' => 'agricultural',
            'parcel_identifier' => 'LAMU/1',
            'marketing_role' => 'authorized_marketer',
            'seller_name' => 'Private seller',
            'seller_contact' => 'seller@example.com',
            'photos' => [UploadedFile::fake()->image('plot.jpg')],
            'authority' => UploadedFile::fake()->create('authority.pdf', 20, 'application/pdf'),
        ], $overrides);
    }

    private function clearCriticalChecks(Property $property, User $staff): void
    {
        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $owner = $property->owners()->create(['user_id' => $seller->id, 'relationship' => 'owner', 'status' => 'pending']);
        app(SellerPortal::class)->verifyLink($owner, $staff, null, 'Test review: owner identity and marketing instruction match.', true);
        $property->authorities()->create([
            'seller_id' => $seller->id, 'scope' => 'both', 'status' => 'active', 'terms' => 'Test-only reviewed marketing authority for this property.',
        ]);
        $desk = app(VerificationDesk::class);
        $desk->seed($property);

        foreach (VerificationCategory::cases() as $category) {
            if (! $category->critical()) {
                continue;
            }

            $desk->review($property->verificationItems()->where('category', $category)->first(), $staff, [
                'state' => VerificationItemState::NotApplicable->value,
                'evidence_note' => 'Not applicable for this listing.',
            ]);
        }
    }

    private function locality(): Locality
    {
        $county = County::query()->create([
            'name' => 'Lamu',
            'slug' => 'lamu',
            'is_active' => true,
        ]);

        return Locality::query()->create([
            'county_id' => $county->id,
            'name' => 'Shela',
            'slug' => 'shela',
            'kind' => 'place',
            'is_active' => true,
        ]);
    }
}
