<?php

namespace Tests\Feature;

use App\Enums\OfferStatus;
use App\Models\County;
use App\Models\Locality;
use App\Models\Offer;
use App\Models\Property;
use App\Models\User;
use App\Notifications\OfferSubmittedNotification;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Foundation\FoundationTestCase;

class PublicListingOfferTest extends FoundationTestCase
{
    public function test_guest_is_redirected_to_login_to_make_an_offer(): void
    {
        $property = $this->publishedProperty();

        $this->get(route('land.offers.create', $property))
            ->assertRedirect(route('login', ['return_to' => route('land.offers.create', $property)]));
    }

    public function test_buyer_can_submit_an_offer_from_a_public_listing(): void
    {
        Notification::fake();

        $property = $this->publishedProperty();
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();

        $this->actingAs($buyer)
            ->post(route('land.offers.store', $property), [
                'amount' => 2_500_000,
                'message' => 'Ready to proceed after survey.',
            ])
            ->assertRedirect(route('buyer.offers.index'))
            ->assertSessionHas('status');

        $offer = Offer::query()->where('property_id', $property->id)->where('buyer_id', $buyer->id)->first();
        $this->assertNotNull($offer);
        $this->assertSame(2_500_000, $offer->amount);

        Notification::assertSentTo($staff, OfferSubmittedNotification::class);
    }

    public function test_staff_notification_opens_the_offer_workspace(): void
    {
        $property = $this->publishedProperty();
        $buyer = User::factory()->withRoles('buyer')->create(['email' => 'offerer@example.test']);
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();

        $this->actingAs($buyer)->post(route('land.offers.store', $property), [
            'amount' => 2_500_000,
            'message' => 'Ready to proceed.',
        ]);

        $offer = Offer::query()->where('property_id', $property->id)->firstOrFail();
        $notice = $staff->fresh()->notifications()->firstOrFail();

        $this->actingAs($staff)
            ->get(route('staff.notifications.open', $notice->id))
            ->assertRedirect(route('staff.offers.show', $offer));

        $this->actingAs($staff)
            ->get(route('staff.offers.show', $offer))
            ->assertOk()
            ->assertSee('KSh '.number_format(2_500_000), false)
            ->assertSee('offerer@example.test', false);
    }

    public function test_staff_offer_notification_opens_even_when_stored_url_host_mismatches_app_url(): void
    {
        config(['app.url' => 'http://localhost']);

        $property = $this->publishedProperty();
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();

        $this->actingAs($buyer)->post(route('land.offers.store', $property), [
            'amount' => 2_500_000,
            'message' => 'Ready to proceed.',
        ]);

        $offer = Offer::query()->where('property_id', $property->id)->firstOrFail();
        $notice = $staff->fresh()->notifications()->firstOrFail();
        $notice->forceFill([
            'data' => array_merge($notice->data, [
                'url' => 'http://lamuacres.test'.route('staff.offers.show', $offer, false),
            ]),
        ])->save();

        $this->actingAs($staff)
            ->get(route('staff.notifications.open', $notice->id))
            ->assertRedirect(route('staff.offers.show', $offer));
    }

    public function test_staff_property_register_lists_offer_counts(): void
    {
        $property = $this->publishedProperty();
        $buyer = User::factory()->withRoles('buyer')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();

        Offer::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'amount' => 1_000_000,
            'currency' => 'KES',
            'status' => OfferStatus::Submitted,
        ]);

        $this->actingAs($staff)
            ->get(route('staff.properties.index'))
            ->assertOk()
            ->assertSee('1 offer', false);
    }

    private function publishedProperty(): Property
    {
        $county = County::query()->create(['name' => 'Lamu', 'slug' => 'lamu', 'is_active' => true]);
        $locality = Locality::query()->create([
            'county_id' => $county->id,
            'name' => 'Shela',
            'slug' => 'shela',
            'kind' => 'place',
            'is_active' => true,
        ]);

        return Property::factory()->published()->listedIn($locality)->create([
            'title' => 'Shela dunes',
            'price_amount' => 3_000_000,
        ]);
    }
}
