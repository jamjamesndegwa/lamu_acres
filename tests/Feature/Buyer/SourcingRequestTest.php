<?php

namespace Tests\Feature\Buyer;

use App\Enums\Amenity;
use App\Enums\LandUse;
use App\Enums\MatchBand;
use App\Enums\PropertyAvailability;
use App\Enums\SourcingRequestStatus;
use App\Models\Property;
use App\Models\SourcingRequest;
use App\Models\User;
use App\Notifications\SourcingAssignedNotification;
use App\Support\Geo\Wkt;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Foundation\FoundationTestCase;

class SourcingRequestTest extends FoundationTestCase
{
    public function test_buyer_staff_assistance_opens_a_full_page_with_the_existing_request_form(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)->get(route('buyer.sourcing.create'))->assertOk()
            ->assertSee('Place a pin where you want land')
            ->assertSee('Submit search request')
            ->assertSee('View your searches')
            ->assertSee('id="sourcing-map"', false)
            ->assertSee('type="hidden" name="centre_latitude"', false)
            ->assertSee('id="radius_km_slider"', false)
            ->assertDontSee('Drawn area')
            ->assertSee('name="location_label"', false)
            ->assertSee('name="notes"', false)
            ->assertSee('action="'.route('buyer.sourcing.store').'"', false)
            ->assertDontSee('data-modal-overlay', false);
    }

    public function test_a_buyer_search_stores_spatial_criteria_and_stays_private(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $other = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)
            ->post(route('buyer.sourcing.store'), $this->criteria([
                'buyer_id' => $other->id,
                'notes' => 'Quiet agricultural land with a borehole if possible.',
            ]))
            ->assertRedirect();

        $request = SourcingRequest::query()->first();

        $this->assertNotNull($request);
        $this->assertMatchesRegularExpression('/^SR-[A-Z0-9]{8}$/', $request->reference);
        $this->assertSame($buyer->id, $request->buyer_id);
        $this->assertSame('POINT(40.9 -2.27)', $request->centre_wkt);
        $this->assertTrue(Wkt::isPolygon((string) $request->boundary_wkt));
        $this->assertSame("ST_GeomFromText('POINT(40.9 -2.27)')", Wkt::geomFromText((string) $request->centre_wkt));
        $this->assertSame(Amenity::Water, $request->amenities()->where('required', true)->first()->amenity);
        $this->assertSame(Amenity::Electricity, $request->amenities()->where('required', false)->first()->amenity);

        $this->actingAs($buyer)->get(route('buyer.sourcing.show', $request))->assertOk()->assertSee($request->reference)->assertSee('Exact matches')->assertSee('Your requirements');
        $this->actingAs($other)->get(route('buyer.sourcing.show', $request))->assertForbidden();
        $this->actingAs($other)->get(route('buyer.sourcing.index'))->assertOk()->assertDontSee($request->reference);
        $this->actingAs(User::factory()->withRoles('broker')->create())->get(route('buyer.sourcing.index'))->assertForbidden();
    }

    public function test_search_criteria_are_validated(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();

        $this->actingAs($buyer)
            ->post(route('buyer.sourcing.store'), [])
            ->assertSessionHasErrors(['location_label', 'centre_latitude']);

        $this->actingAs($buyer)
            ->post(route('buyer.sourcing.store'), $this->criteria(['radius_km' => 0]))
            ->assertSessionHasErrors('radius_km');

        $this->actingAs($buyer)
            ->post(route('buyer.sourcing.store'), $this->criteria(['min_acreage' => 5, 'max_acreage' => 1]))
            ->assertSessionHasErrors('max_acreage');

        $this->actingAs($buyer)
            ->post(route('buyer.sourcing.store'), $this->criteria([
                'polygon' => [
                    ['lat' => -2.28, 'lng' => 40.89],
                    ['lat' => -2.28, 'lng' => 40.91],
                ],
            ]))
            ->assertSessionHasErrors('polygon');

        $this->assertSame(0, SourcingRequest::query()->count());
    }

    public function test_matching_separates_exact_and_near_properties_without_using_unavailable_land(): void
    {
        $buyer = User::factory()->withRoles('buyer')->create();
        $exact = $this->plot('Exact holding', -2.27, 40.9, 5, 1_000_000);
        $near = $this->plot('Near holding', -2.29, 40.9, 5, 1_000_000);
        $this->plot('Distant holding', -2.5, 40.9, 5, 1_000_000);
        $this->plot('Too large holding', -2.27, 40.901, 80, 1_000_000);
        $unavailable = $this->plot('Closed holding', -2.27, 40.902, 5, 1_000_000);
        $unavailable->forceFill(['availability' => PropertyAvailability::Unavailable])->save();

        $this->actingAs($buyer)
            ->post(route('buyer.sourcing.store'), $this->criteria())
            ->assertRedirect();

        $request = SourcingRequest::query()->first();
        $bands = $request->matches()->with('property')->get()->mapWithKeys(
            fn ($match) => [$match->property->title => $match->band],
        );

        $this->assertSame(MatchBand::Exact, $bands['Exact holding']);
        $this->assertSame(MatchBand::Near, $bands['Near holding']);
        $this->assertArrayNotHasKey('Distant holding', $bands->all());
        $this->assertArrayNotHasKey('Too large holding', $bands->all());
        $this->assertArrayNotHasKey('Closed holding', $bands->all());

        $this->actingAs($buyer)
            ->get(route('buyer.sourcing.show', $request))
            ->assertOk()
            ->assertSee('Exact holding')
            ->assertSee('Near holding')
            ->assertDontSee('Distant holding')
            ->assertDontSee('40.2222222');

        $this->actingAs($buyer)
            ->put(route('buyer.sourcing.update', $request), $this->criteria(['notes' => 'Prefer a surveyed boundary.']))
            ->assertRedirect(route('buyer.sourcing.show', $request));

        $this->assertSame('Prefer a surveyed boundary.', $request->refresh()->notes);
        $this->assertTrue($request->activities()->where('summary', 'like', '%updated.%')->exists());
    }

    public function test_staff_assignment_follows_the_sourcing_desk(): void
    {
        Notification::fake();

        $buyer = User::factory()->withRoles('buyer')->create();
        $desk = User::factory()->withRoles('staff')->withPermissions('staff.sourcing')->create();
        $agent = User::factory()->withRoles('staff')->create();
        $outsider = User::factory()->withRoles('staff')->create();

        $this->actingAs($buyer)->post(route('buyer.sourcing.store'), $this->criteria());
        $request = SourcingRequest::query()->first();

        $this->actingAs($outsider)->get(route('staff.sourcing.index'))->assertForbidden();
        $this->actingAs($outsider)->get(route('staff.sourcing.show', $request))->assertForbidden();
        $this->actingAs($buyer)->get(route('staff.sourcing.show', $request))->assertForbidden();

        $this->actingAs($desk)
            ->put(route('staff.sourcing.assign', $request), ['assigned_to' => $agent->id])
            ->assertRedirect()
            ->assertSessionHas('status', 'Sourcing request assigned.');

        $request->refresh();
        $this->assertSame($agent->id, (int) $request->assigned_to);
        $this->assertSame(SourcingRequestStatus::InProgress, $request->status);
        Notification::assertSentTo($agent, SourcingAssignedNotification::class);

        $this->actingAs($agent)->get(route('staff.sourcing.show', $request))->assertOk()->assertSee($request->reference);
        $this->actingAs($agent)
            ->put(route('staff.sourcing.assign', $request), ['assigned_to' => $desk->id])
            ->assertForbidden();

        $this->actingAs($agent)
            ->put(route('staff.sourcing.update', $request), ['status' => SourcingRequestStatus::Matched->value])
            ->assertRedirect();

        $this->assertSame(SourcingRequestStatus::Matched, $request->refresh()->status);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function criteria(array $overrides = []): array
    {
        return array_merge([
            'location_label' => 'Shela',
            'centre_latitude' => -2.27,
            'centre_longitude' => 40.9,
            'radius_km' => 5,
            'polygon' => [
                ['lat' => -2.28, 'lng' => 40.89],
                ['lat' => -2.28, 'lng' => 40.91],
                ['lat' => -2.26, 'lng' => 40.91],
                ['lat' => -2.26, 'lng' => 40.89],
            ],
            'min_acreage' => 2,
            'max_acreage' => 10,
            'maximum_budget' => 2_000_000,
            'intended_use' => LandUse::Agricultural->value,
            'mandatory' => [Amenity::Water->value],
            'preferred' => [Amenity::Electricity->value],
            'notes' => 'Road frontage is useful.',
        ], $overrides);
    }

    private function plot(string $title, float $latitude, float $longitude, float $acreage, int $price): Property
    {
        return Property::factory()->published()->create([
            'title' => $title,
            'public_latitude' => $latitude,
            'public_longitude' => $longitude,
            'restricted_latitude' => -2.1111111,
            'restricted_longitude' => 40.2222222,
            'acreage' => $acreage,
            'price_amount' => $price,
            'land_use' => LandUse::Agricultural,
            'amenities' => [Amenity::Water->value],
        ]);
    }
}
