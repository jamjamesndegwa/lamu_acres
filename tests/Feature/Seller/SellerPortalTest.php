<?php

namespace Tests\Feature\Seller;

use App\Enums\AuthorityStatus;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\PropertyStatus;
use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Services\Verification\VerificationDesk;
use App\Models\MarketingAuthority;
use App\Models\Offer;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\PropertyTransaction;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Foundation\FoundationTestCase;

class SellerPortalTest extends FoundationTestCase
{
    public function test_only_a_verified_or_authorised_relationship_can_see_a_property(): void
    {
        $seller = User::factory()->withRoles('seller')->create();
        $other = User::factory()->withRoles('seller')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties', 'staff.verification')->create();

        $this->actingAs($seller)
            ->post(route('seller.properties.store'), ['title' => 'Shela holding', 'locality' => 'Shela', 'acreage' => 4])
            ->assertRedirect();

        $property = Property::query()->where('title', 'Shela holding')->first();
        $this->assertSame(1, Property::query()->where('title', 'Shela holding')->count());

        $this->actingAs($seller)->get(route('seller.properties.index'))->assertOk()->assertDontSee('Shela holding');
        $this->actingAs($other)->get(route('seller.properties.show', $property))->assertForbidden();

        $link = PropertyOwner::query()->where('property_id', $property->id)->first();
        $this->actingAs($staff)
            ->post(route('staff.owners.verify', $link))
            ->assertRedirect();

        $this->actingAs($seller)->get(route('seller.properties.index'))->assertOk()->assertSee('Shela holding');

        $before = Property::query()->count();
        $this->actingAs($staff)
            ->post(route('staff.properties.owners.store', $property), [
                'user_id' => $other->id,
                'relationship' => OwnerRelationship::Authorized->value,
            ])
            ->assertRedirect();

        $this->assertSame($before, Property::query()->count());
        $this->actingAs($other)->get(route('seller.properties.show', $property))->assertOk()->assertSee('Shela holding');

        PropertyOwner::query()->where('user_id', $other->id)->update(['status' => OwnerLinkStatus::Revoked]);
        $this->actingAs($other)->get(route('seller.properties.show', $property))->assertForbidden();
    }

    public function test_seller_instructions_do_not_sell_the_property_or_pay_commission(): void
    {
        [$seller, $staff, $property] = $this->verifiedProperty();
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'staff.buyers')->create();

        $this->actingAs($seller)
            ->post(route('seller.properties.documents.store', $property), [
                'document' => UploadedFile::fake()->create('title-deed.pdf', 20, 'application/pdf'),
            ])
            ->assertRedirect();

        $document = $property->ownershipDocuments()->first();
        $this->actingAs(User::factory()->withRoles('seller')->create())
            ->get(route('seller.documents.download', $document))
            ->assertForbidden();

        $this->actingAs($seller)
            ->post(route('seller.properties.authority', $property), [
                'terms' => 'I authorise Lamu Acres to market this land on the stated terms.',
                'status' => AuthorityStatus::Active->value,
            ])
            ->assertRedirect();

        $this->assertSame(AuthorityStatus::Submitted, MarketingAuthority::query()->first()->status);

        $this->actingAs($seller)
            ->post(route('seller.properties.instructions', $property), [
                'kind' => 'listing',
                'body' => 'Please list this land for sale.',
            ])
            ->assertRedirect();

        $this->assertSame(PropertyStatus::Draft, $property->refresh()->status);

        $this->actingAs($seller)
            ->post(route('seller.properties.instructions', $property), [
                'kind' => 'availability',
                'body' => 'Please mark sold today.',
                'requested_availability' => 'sold',
            ])
            ->assertSessionHasErrors('requested_availability');

        foreach (VerificationCategory::cases() as $category) {
            if (! $category->critical()) {
                continue;
            }

            app(VerificationDesk::class)->review($property->verificationItems()->where('category', $category)->first(), $staff, [
                'state' => VerificationItemState::NotApplicable->value,
                'evidence_note' => 'Not applicable for this listing.',
            ]);
        }

        $this->actingAs($staff)->post(route('staff.properties.listing', $property))->assertRedirect();
        $this->assertSame(PropertyStatus::Published, $property->refresh()->status);

        $buyer = User::factory()->withRoles('buyer')->create();
        $offer = Offer::query()->create([
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'amount' => 2_000_000,
            'message' => 'Buyer private offer note.',
        ]);

        $this->actingAs($sales)
            ->post(route('staff.offers.present', $offer), ['summary' => 'An offer of KSh 2,000,000 has been received.'])
            ->assertRedirect();

        $presentation = $offer->presentations()->first();
        $this->actingAs($seller)
            ->post(route('seller.offers.respond', $presentation), ['seller_response' => 'accept'])
            ->assertRedirect();

        $property->refresh();
        $this->assertSame(PropertyStatus::Published, $property->status);
        $this->assertSame(0, PropertyTransaction::query()->count());
        $this->assertSame('submitted', $offer->refresh()->status->value);

        $this->actingAs($sales)
            ->post(route('staff.properties.transactions.store', $property), [
                'status' => 'in_progress',
                'amount' => 2000000,
                'statement' => 'Deposit recorded. No commission is payable from this screen.',
            ])
            ->assertRedirect();

        $this->actingAs($seller)
            ->get(route('seller.transactions.show', PropertyTransaction::query()->first()))
            ->assertOk()
            ->assertSee('Deposit recorded');

        $this->assertNotSame(PropertyStatus::Sold, $property->refresh()->status);

        $this->actingAs($seller)
            ->put(route('staff.properties.transition', $property), ['status' => PropertyStatus::Sold->value])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: User, 2: Property}
     */
    private function verifiedProperty(): array
    {
        $seller = User::factory()->withRoles('seller')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();

        $this->actingAs($seller)->post(route('seller.properties.store'), ['title' => 'Hindi block']);
        $property = Property::query()->where('title', 'Hindi block')->first();
        $this->actingAs($staff)->post(route('staff.owners.verify', $property->owners()->first()));

        return [$seller, $staff, $property->refresh()];
    }
}
