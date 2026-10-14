<?php

namespace Tests\Feature\Intelligence;

use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\ResearchReport;
use App\Models\User;
use App\Services\Intelligence\PriceObservatory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class MarketIntelligenceTest extends FoundationTestCase
{
    public function test_asking_prices_stay_separate_from_verified_sales_and_reports_follow_publication_rules(): void
    {
        Storage::fake('local');

        $researcher = User::factory()->withRoles('staff')->withPermissions('staff.intelligence')->create();
        $editor = User::factory()->withRoles('staff')->withPermissions('intelligence.publish')->create();
        $subscriber = User::factory()->withRoles('buyer')->withInvestorPass()->create();
        $locality = $this->locality();

        foreach ([1_000_000, 3_000_000, 5_000_000] as $price) {
            Property::factory()->published()->listedIn($locality)->create([
                'price_amount' => $price,
                'acreage' => 1,
            ]);
        }

        $median = app(PriceObservatory::class)->askingByLocality()->first();
        $this->assertSame(3_000_000, $median['median']);
        $this->assertSame(3, $median['sample']);

        $this->actingAs($researcher)->post(route('staff.prices.sales.store'), [
            'locality_id' => $locality->id,
            'acreage' => 1,
            'price_amount' => 100,
            'sold_on' => now()->toDateString(),
            'source_name' => 'Conveyance file',
            'evidence' => UploadedFile::fake()->create('sale.pdf', 20, 'application/pdf'),
        ])->assertRedirect();

        $sale = \App\Models\VerifiedSale::query()->firstOrFail();
        $this->actingAs($researcher)->post(route('staff.prices.sales.submit', $sale))->assertRedirect();
        $this->actingAs($editor)->post(route('staff.prices.sales.publish', $sale))->assertRedirect();

        $this->assertSame(3_000_000, app(PriceObservatory::class)->askingByLocality()->first()['median']);

        auth()->logout();

        $this->get(route('intelligence.prices'))
            ->assertOk()
            ->assertSee('Sample size: 3')
            ->assertSee('not achieved sale prices')
            ->assertDontSee('KSh 3,000,000')
            ->assertDontSee('guaranteed');

        $this->actingAs($subscriber)
            ->get(route('intelligence.prices'))
            ->assertOk()
            ->assertSee('Median asking price per acre: KSh 3,000,000')
            ->assertSee('Verified sale')
            ->assertSee('not mixed into the asking-price median');

        $this->actingAs($researcher)->post(route('staff.planning.store'), [
            'title' => 'Shela land-use note',
            'area' => 'Shela',
            'instrument_status' => 'in_force',
            'source_document' => 'County plan extract',
            'restrictions' => 'No industrial use was stated in the extract.',
            'permitted_uses' => 'Residential plots were listed.',
        ])->assertRedirect();

        $record = \App\Models\PlanningRecord::query()->firstOrFail();
        $this->get(route('intelligence.planning.show', $record))->assertNotFound();
        $this->actingAs($researcher)->post(route('staff.planning.submit', $record))->assertRedirect();
        $this->actingAs($researcher)->post(route('staff.planning.publish', $record))->assertForbidden();
        $this->actingAs($editor)->post(route('staff.planning.publish', $record))->assertRedirect();

        auth()->logout();

        $this->get(route('intelligence.planning.show', $record))
            ->assertOk()
            ->assertDontSee('No industrial use');

        $this->actingAs($subscriber)
            ->get(route('intelligence.planning.show', $record))
            ->assertOk()
            ->assertSee('No industrial use');

        $this->actingAs($researcher)->post(route('staff.reports.store'), [
            'title' => 'Coastal research note',
            'summary' => 'A short public summary.',
            'body' => 'The confidential finding is the port dredging note.',
            'citation_label' => 'LAPSSET authority notice',
            'locality_ids' => [$locality->id],
        ])->assertRedirect();

        $report = ResearchReport::query()->firstOrFail();
        $this->actingAs($researcher)->post(route('staff.reports.submit', $report))->assertRedirect();
        $this->actingAs($researcher)->post(route('staff.reports.publish', $report))->assertForbidden();
        $this->actingAs($editor)->post(route('staff.reports.publish', $report))->assertRedirect();

        auth()->logout();

        $this->get(route('intelligence.reports.show', $report))
            ->assertOk()
            ->assertSee('A short public summary.')
            ->assertSee('Research date')
            ->assertSee('Last review')
            ->assertSee('Project status')
            ->assertSee('Uncertainty')
            ->assertSee('A source is on file')
            ->assertDontSee('port dredging note')
            ->assertDontSee('LAPSSET authority notice');

        $this->actingAs($editor)->put(route('staff.reports.update', $report), [
            'title' => 'Coastal research note',
            'summary' => 'A short public summary.',
            'body' => 'The confidential finding is the port dredging note. Revised sentence.',
            'citation_label' => 'LAPSSET authority notice',
            'locality_ids' => [$locality->id],
        ])->assertRedirect();

        $this->assertTrue($report->audits()->where('action', 'revised')->exists());

        $this->actingAs($subscriber)
            ->get(route('intelligence.reports.show', $report))
            ->assertOk()
            ->assertSee('port dredging note')
            ->assertSee('LAPSSET authority notice')
            ->assertSee('revised');
    }

    private function locality(): Locality
    {
        $county = County::query()->create(['name' => 'Lamu', 'slug' => 'lamu', 'is_active' => true]);

        return Locality::query()->create([
            'county_id' => $county->id,
            'name' => 'Shela',
            'slug' => 'shela',
            'kind' => 'place',
            'is_active' => true,
        ]);
    }
}
