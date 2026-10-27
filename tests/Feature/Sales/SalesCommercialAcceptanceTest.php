<?php

namespace Tests\Feature\Sales;

use App\Enums\BrokerIntroductionStatus;
use App\Enums\CapabilityApplicationStatus;
use App\Enums\ClosingEvidenceState;
use App\Enums\OfferStatus;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\PropertyStatus;
use App\Enums\SaleMode;
use App\Enums\SalesOrigin;
use App\Enums\SalesStage;
use App\Models\BrokerIntroduction;
use App\Models\CommissionAgreement;
use App\Models\County;
use App\Models\Locality;
use App\Models\Offer;
use App\Models\Property;
use App\Models\PropertyOwner;
use App\Models\SalesOfferEntry;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Inventory\PropertyInventory;
use App\Services\Sales\ClosingDesk;
use App\Services\Sales\SalesPipeline;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class SalesCommercialAcceptanceTest extends FoundationTestCase
{
    public function test_buyer_offer_journey_closes_partial_sale_with_traceable_inventory(): void
    {
        Notification::fake();
        $this->freezeTime();

        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'staff.properties')->create();
        $property = $this->partialFortyAcreListing($seller);

        $this->actingAs($buyer)->post(route('land.offers.store', $property), [
            'amount' => 32_000_000,
            'requested_acreage' => 32,
            'message' => 'Offer for 32 acres at asking rate.',
        ])->assertRedirect(route('buyer.offers.index'));

        $offer = Offer::query()->sole();
        $this->assertEquals(32.0, (float) $offer->requested_acreage);
        $this->assertSame(32_000_000, $offer->amount);

        $this->actingAs($sales)->post(route('staff.offers.present', $offer), [
            'summary' => 'Buyer offer for staff and seller review.',
        ])->assertRedirect();

        $presentation = $offer->presentations()->latest('id')->firstOrFail();
        $this->actingAs($seller)->post(route('seller.offers.respond', $presentation), [
            'seller_response' => 'accept',
        ])->assertRedirect();
        $this->assertSame(OfferStatus::Accepted, $offer->refresh()->status);

        $this->actingAs($sales)
            ->post(route('staff.offers.sales-case', $offer))
            ->assertRedirect(route('staff.sales.show', SalesTransaction::query()->sole()));

        $transaction = SalesTransaction::query()->sole();
        $this->assertSame(SalesOrigin::Offer, $transaction->origin);
        $this->assertSame($buyer->id, $transaction->buyer_id);
        $this->assertEquals(32.0, (float) $transaction->acreage);
        $this->assertNull($transaction->agreed_price);
        $this->assertDatabaseHas('sales_offer_entries', [
            'sales_transaction_id' => $transaction->id,
            'offer_id' => $offer->id,
            'amount' => 32_000_000,
        ]);

        $transaction = $this->prepareForClosing($transaction, $sales, 32_000_000);
        $this->actingAs($sales)
            ->post(route('staff.sales.complete', $transaction), ['lock_version' => $transaction->lock_version])
            ->assertRedirect();

        $property->refresh();
        $snapshot = app(PropertyInventory::class)->inventorySnapshot($property);

        $this->assertSame(SalesStage::Completed, $transaction->refresh()->stage);
        $this->assertSame(32_000_000, $transaction->agreed_price);
        $this->assertEquals(8.0, $snapshot['remaining_acreage']);
        $this->assertEquals(32.0, $snapshot['sold_acreage']);
        $this->assertSame($transaction->id, $property->portions()->where('status', PropertyStatus::Sold)->sole()->sales_transaction_id);
    }

    public function test_broker_originated_offer_opens_sales_case_with_buyer_and_broker_attribution(): void
    {
        Notification::fake();

        $seller = User::factory()->withRoles('seller')->withVerifiedCapability('seller')->create();
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'staff.properties')->create();
        $property = $this->partialFortyAcreListing($seller);

        $introduction = BrokerIntroduction::factory()->create([
            'broker_id' => $broker->id,
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'status' => BrokerIntroductionStatus::Active,
        ]);
        $introduction->properties()->sync([$property->id]);

        $this->actingAs($broker)->post(route('broker.offers.store'), [
            'broker_introduction_id' => $introduction->id,
            'property_id' => $property->id,
            'amount' => 30_000_000,
            'requested_acreage' => 30,
            'message' => 'Buyer is ready to proceed.',
        ])->assertRedirect(route('broker.referrals.show', $introduction));

        $offer = Offer::query()->sole();
        $this->assertSame($buyer->id, $offer->buyer_id);
        $this->assertSame($broker->id, $offer->submitted_by_broker_id);

        $this->actingAs($sales)
            ->post(route('staff.offers.sales-case', $offer))
            ->assertRedirect();

        $transaction = SalesTransaction::query()->sole();
        $this->assertSame(SalesOrigin::Introduction, $transaction->origin);
        $this->assertSame($buyer->id, $transaction->buyer_id);
        $this->assertSame($introduction->id, $transaction->broker_introduction_id);
        $this->assertSame(1, SalesOfferEntry::query()->where('offer_id', $offer->id)->count());

        $this->assertSame(0, CommissionAgreement::query()->where('broker_id', $broker->id)->count());
        $this->assertNotSame(SalesStage::Completed, $transaction->stage);
    }

    public function test_expired_broker_cannot_submit_buyer_offer_but_can_open_agreements(): void
    {
        $this->freezeTime();
        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $application = $broker->capabilityApplications()->firstOrFail();
        $profile = $application->verification;
        $profile['certificate_expires_on'] = now()->subDay()->toDateString();
        $application->forceFill(['verification' => $profile])->save();

        $property = $this->partialFortyAcreListing();
        $buyer = User::factory()->withRoles('buyer')->create();
        $introduction = BrokerIntroduction::factory()->create([
            'broker_id' => $broker->id,
            'buyer_id' => $buyer->id,
            'property_id' => $property->id,
            'status' => BrokerIntroductionStatus::Active,
        ]);

        $this->actingAs($broker)->post(route('broker.offers.store'), [
            'broker_introduction_id' => $introduction->id,
            'property_id' => $property->id,
            'amount' => 1_000_000,
            'requested_acreage' => 5,
        ])->assertSessionHasErrors('capability');

        $this->actingAs($broker)->get(route('broker.agreements.index'))->assertOk();
        $this->assertDatabaseCount('offers', 0);
    }

    public function test_broker_pending_resubmission_restores_workspace_access_without_offer_writes(): void
    {
        Notification::fake();
        Storage::fake('local');

        $broker = User::factory()->withRoles('broker')->withVerifiedCapability('broker')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.brokerage')->create();
        $application = $broker->capabilityApplications()->firstOrFail();

        $this->actingAs($staff)->put(route('staff.applications.update', $application), [
            'status' => CapabilityApplicationStatus::InformationRequired->value,
            'decision_reason' => 'Upload a renewed practising certificate before credentials are restored.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($broker)->post(route('account.applications.store'), $this->brokerApplicationPayload())->assertSessionHasNoErrors();
        $this->assertSame(CapabilityApplicationStatus::Pending, $application->fresh()->status);
        $this->assertTrue($broker->fresh()->hasRole('broker'));

        $property = $this->partialFortyAcreListing();
        $introduction = BrokerIntroduction::factory()->create([
            'broker_id' => $broker->id,
            'buyer_id' => User::factory()->withRoles('buyer')->create()->id,
            'property_id' => $property->id,
            'status' => BrokerIntroductionStatus::Active,
        ]);

        $this->actingAs($broker)->post(route('broker.offers.store'), [
            'broker_introduction_id' => $introduction->id,
            'property_id' => $property->id,
            'amount' => 1_000_000,
            'requested_acreage' => 5,
        ])->assertSessionHasErrors('capability');

        $this->actingAs($broker)->get(route('broker.agreements.index'))->assertOk();
    }

    private function partialFortyAcreListing(?User $seller = null): Property
    {
        $county = County::query()->create(['name' => 'Lamu', 'slug' => 'lamu-accept', 'is_active' => true]);
        $locality = Locality::query()->create([
            'county_id' => $county->id,
            'name' => 'Manda',
            'slug' => 'manda-accept',
            'kind' => 'place',
            'is_active' => true,
        ]);

        $property = Property::factory()->published()->listedIn($locality)->create([
            'acreage' => 40,
            'offered_acreage' => 40,
            'minimum_acreage' => 5,
            'price_amount' => 40_000_000,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
            'submitted_by' => $seller?->id,
        ]);

        if ($seller !== null) {
            PropertyOwner::query()->create([
                'property_id' => $property->id,
                'user_id' => $seller->id,
                'relationship' => OwnerRelationship::Owner,
                'status' => OwnerLinkStatus::Verified,
            ]);
        }

        return $property;
    }

    /**
     * @return array<string, mixed>
     */
    private function brokerApplicationPayload(): array
    {
        return [
            'capability' => 'broker',
            'legal_name' => 'Renewed Broker Applicant',
            'applicant_type' => 'individual',
            'statement' => 'I provide current evidence for the partner capability review.',
            'earb_registration_number' => 'TEST-RENEW-123',
            'certificate_expires_on' => now()->addYear()->toDateString(),
            'identity' => UploadedFile::fake()->create('identity.pdf', 10, 'application/pdf'),
            'practising_certificate' => UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf'),
        ];
    }

    private function prepareForClosing(SalesTransaction $transaction, User $sales, int $price): SalesTransaction
    {
        $pipeline = app(SalesPipeline::class);
        $closing = app(ClosingDesk::class);

        foreach ([
            SalesStage::Viewing,
            SalesStage::Offer,
            SalesStage::Negotiation,
            SalesStage::OfferAccepted,
            SalesStage::Contracting,
            SalesStage::CompletionPending,
        ] as $stage) {
            $transaction = $pipeline->advance($transaction, $stage, $sales, $transaction->lock_version);
        }

        $transaction = $pipeline->agreePrice($transaction, $price, $sales, $transaction->lock_version);
        $closing->review($transaction, $sales, 'authorized', 'Authorized for commercial acceptance.');
        foreach (array_keys(ClosingDesk::EVIDENCE) as $code) {
            $closing->evidence($transaction, $sales, $code, ClosingEvidenceState::Accepted, 'Accepted.');
        }

        return $transaction->refresh();
    }
}
