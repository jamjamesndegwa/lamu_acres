<?php

namespace Tests\Feature\Sales;

use App\Enums\CommissionStatus;
use App\Enums\SaleMode;
use App\Models\CommissionAgreement;
use App\Models\Property;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Services\Sales\CommissionDesk;
use App\Services\Sales\SalesPipeline;
use Tests\Feature\Foundation\FoundationTestCase;

class CommissionAgreementTest extends FoundationTestCase
{
    public function test_registration_and_acceptance_do_not_earn_commission_and_duplicate_claims_are_refused(): void
    {
        [$sales, $broker, $transaction] = $this->deal();

        $this->actingAs($sales)
            ->post(route('staff.commissions.store', $transaction), [
                'broker_id' => $broker->id,
                'rate_percent' => 10,
                'funding_party' => 'seller',
                'protection_ends_at' => now()->addMonths(6)->toDateString(),
                'payment_due_days' => 30,
            ])
            ->assertRedirect();

        $agreement = CommissionAgreement::query()->firstOrFail();
        $this->assertSame(CommissionStatus::Proposed, $agreement->status);
        $this->assertNull($agreement->earned_amount);

        $this->actingAs($sales)->post(route('staff.commissions.accept', $agreement))->assertRedirect();
        $this->assertSame(CommissionStatus::Accepted, $agreement->refresh()->status);
        $this->assertNull($agreement->earned_amount);
        $this->assertSame(SalesTransaction::query()->first()->stage->value, 'enquiry');

        $this->actingAs($sales)
            ->post(route('staff.commissions.store', $transaction), [
                'broker_id' => $broker->id,
                'fixed_amount' => 50000,
                'funding_party' => 'seller',
                'protection_ends_at' => now()->addMonths(6)->toDateString(),
            ])
            ->assertSessionHasErrors('broker_id');

        $this->assertSame(1, CommissionAgreement::query()->where('open_claim', 'open')->count());
    }

    public function test_a_revised_price_is_used_only_when_the_sale_completes_and_cancellation_earns_nothing(): void
    {
        [$sales, $broker, $transaction] = $this->deal();
        $desk = app(CommissionDesk::class);
        $agreement = $desk->propose($transaction, $sales, [
            'broker_id' => $broker->id,
            'rate_percent' => 10,
            'funding_party' => 'seller',
            'protection_ends_at' => now()->addMonths(3),
        ]);
        $desk->accept($agreement, $sales);

        $pipeline = app(SalesPipeline::class);
        $transaction = $pipeline->agreePrice($transaction, 1_000_000, $sales, $transaction->lock_version);
        $transaction = $pipeline->agreePrice($transaction, 800_000, $sales, $transaction->lock_version);
        $this->assertNull($desk->eligibleAmount($agreement->refresh(), $transaction->refresh()));

        $transaction = $pipeline->outcome($transaction, \App\Enums\SalesOutcome::Cancelled, $sales, $transaction->lock_version, 'The buyer withdrew before contract.');
        $this->assertNull($desk->eligibleAmount($agreement->refresh(), $transaction));
        $this->assertSame(CommissionStatus::Accepted, $agreement->refresh()->status);

        $revised = $desk->revise($agreement, $sales, ['rate_percent' => 8]);
        $this->assertSame(CommissionStatus::Superseded, $agreement->refresh()->status);
        $this->assertSame(2, $revised->version);
        $this->assertSame(CommissionStatus::Proposed, $revised->status);
    }

    /**
     * @return array{0: User, 1: User, 2: SalesTransaction}
     */
    private function deal(): array
    {
        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'commissions.propose')->create();
        $broker = User::factory()->withRoles('broker')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->published()->create([
            'acreage' => 10,
            'offered_acreage' => 10,
            'minimum_acreage' => 2,
            'listing_terms' => ['sale_mode' => SaleMode::Partial->value],
        ]);
        $seller = User::factory()->withRoles('seller')->create();
        $instruction = \App\Models\SaleInstruction::query()->create([
            'property_id' => $property->id,
            'seller_id' => $seller->id,
            'kind' => 'general',
            'body' => 'Market this land.',
        ]);

        $transaction = app(SalesPipeline::class)->open($sales, [
            'origin' => 'instruction',
            'property_id' => $property->id,
            'buyer_id' => $buyer->id,
            'sale_instruction_id' => $instruction->id,
            'acreage' => 4,
        ]);

        return [$sales, $broker, $transaction];
    }
}
