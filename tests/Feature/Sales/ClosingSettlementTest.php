<?php

namespace Tests\Feature\Sales;

use App\Enums\ClosingEvidenceState;
use App\Enums\CommissionStatus;
use App\Enums\PaymentReconciliation;
use App\Enums\PropertyStatus;
use App\Enums\SalesStage;
use App\Models\CommissionAgreement;
use App\Models\CommissionPayment;
use App\Models\Property;
use App\Models\SaleInstruction;
use App\Models\SalesAudit;
use App\Models\SalesTransaction;
use App\Models\User;
use App\Notifications\CommissionSettlementNotification;
use App\Notifications\SaleCompletedNotification;
use App\Services\Sales\ClosingDesk;
use App\Services\Sales\CommissionDesk;
use App\Services\Sales\SalesPipeline;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Foundation\FoundationTestCase;

class ClosingSettlementTest extends FoundationTestCase
{
    public function test_completion_is_authorized_once_and_partial_sales_leave_the_remaining_land(): void
    {
        Notification::fake();

        $sales = User::factory()->withRoles('staff')->withPermissions('staff.sales', 'commissions.propose')->create();
        $finance = User::factory()->withRoles('staff')->withPermissions('staff.finance', 'commissions.approve', 'commissions.record')->create();
        $broker = User::factory()->withRoles('broker')->create();
        $seller = User::factory()->withRoles('seller')->create();
        $property = Property::factory()->published()->create(['title' => 'Parent parcel', 'acreage' => 10]);
        $instruction = SaleInstruction::query()->create([
            'property_id' => $property->id,
            'seller_id' => $seller->id,
            'kind' => 'general',
            'body' => 'Sell portions as buyers are found.',
        ]);

        $pipeline = app(SalesPipeline::class);
        $closing = app(ClosingDesk::class);
        $commissions = app(CommissionDesk::class);

        $first = $pipeline->open($sales, [
            'origin' => 'instruction',
            'property_id' => $property->id,
            'sale_instruction_id' => $instruction->id,
            'acreage' => 4,
        ]);
        $agreement = $commissions->propose($first, $sales, [
            'broker_id' => $broker->id,
            'rate_percent' => 10,
            'funding_party' => 'seller',
            'protection_ends_at' => now()->addYear(),
        ]);
        $commissions->accept($agreement, $sales);

        $this->actingAs($sales)
            ->post(route('staff.sales.complete', $first), ['lock_version' => $first->lock_version])
            ->assertSessionHasErrors('stage');
        $this->assertSame(PropertyStatus::Published, $property->refresh()->status);

        $first = $this->prepare($first, $sales, 800_000);
        $stale = $first->lock_version;
        $first = $pipeline->agreePrice($first, 900_000, $sales, $stale);

        $this->actingAs($sales)
            ->post(route('staff.sales.complete', $first), ['lock_version' => $stale])
            ->assertSessionHasErrors('lock_version');

        $this->actingAs($finance)->post(route('staff.sales.complete', $first), ['lock_version' => $first->lock_version])->assertForbidden();

        $this->actingAs($sales)
            ->post(route('staff.sales.complete', $first), ['lock_version' => $first->refresh()->lock_version])
            ->assertRedirect();

        $property->refresh();
        $this->assertSame(PropertyStatus::Published, $property->status);
        $this->assertEquals(6.0, $property->remainingAcreage());
        $this->assertSame(PropertyStatus::Sold, $property->portions()->first()->status);
        $this->assertSame(SalesStage::Completed, $first->refresh()->stage);
        $this->assertSame(90_000, $agreement->refresh()->earned_amount);
        $this->assertSame(CommissionStatus::Earned, $agreement->status);
        Notification::assertSentTo($broker, SaleCompletedNotification::class);

        $second = $pipeline->open($sales, [
            'origin' => 'instruction',
            'property_id' => $property->id,
            'sale_instruction_id' => $instruction->id,
            'acreage' => 8,
        ]);
        $second = $this->prepare($second, $sales, 500_000);
        $this->actingAs($sales)
            ->post(route('staff.sales.complete', $second), ['lock_version' => $second->lock_version])
            ->assertSessionHasErrors('acreage');
        $this->assertEquals(6.0, $property->refresh()->remainingAcreage());
        $this->assertSame(1, $property->portions()->count());

        $this->actingAs($sales)->post(route('staff.settlements.approve', $agreement))->assertForbidden();
        $this->actingAs($finance)->post(route('staff.settlements.approve', $agreement))->assertRedirect();
        $this->assertSame(CommissionStatus::Payable, $agreement->refresh()->status);

        $this->actingAs($sales)->post(route('staff.settlements.initiate', $agreement), [
            'reference' => 'PAY-100',
            'reconciliation' => 'unmatched',
        ])->assertForbidden();

        $this->actingAs($finance)->post(route('staff.settlements.initiate', $agreement), [
            'reference' => 'PAY-100',
            'reconciliation' => PaymentReconciliation::Unmatched->value,
        ])->assertRedirect();

        $this->actingAs($finance)->post(route('staff.settlements.pay', $agreement), [
            'reconciliation' => PaymentReconciliation::Matched->value,
        ])->assertRedirect();

        $this->actingAs($finance)->post(route('staff.settlements.pay', $agreement), [
            'reconciliation' => PaymentReconciliation::Matched->value,
        ])->assertSessionHasErrors('payment');

        $this->assertSame(1, CommissionPayment::query()->count());
        $this->assertSame(CommissionStatus::Paid, $agreement->refresh()->status);
        $this->assertSame('paid', CommissionPayment::query()->first()->status);
        Notification::assertSentTo($broker, CommissionSettlementNotification::class);

        foreach (['sale_completed', 'commission_earned', 'commission_payable', 'commission_payment_initiated', 'commission_paid'] as $event) {
            $this->assertTrue(SalesAudit::query()->where('event', $event)->exists(), $event);
        }

        $this->assertNotNull(SalesTransaction::query()->find($first->id));
        $this->assertNotNull($property->portions()->first());
    }

    private function prepare(SalesTransaction $transaction, User $sales, int $price): SalesTransaction
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
        $closing->review($transaction, $sales, 'authorized', 'The completion file supports this sale.');
        foreach (array_keys(ClosingDesk::EVIDENCE) as $code) {
            $closing->evidence($transaction, $sales, $code, ClosingEvidenceState::Accepted, 'Accepted for completion.');
        }

        return $transaction->refresh();
    }
}
