<?php

namespace Database\Seeders;

use App\Enums\DevelopmentCategory;
use App\Enums\DevelopmentProjectStatus;
use App\Enums\PlanningInstrumentStatus;
use App\Enums\PublicationStatus;
use App\Models\County;
use App\Models\DevelopmentMilestone;
use App\Models\DevelopmentProject;
use App\Models\ImageryComparison;
use App\Models\ImageryScene;
use App\Models\Locality;
use App\Models\PlanningRecord;
use App\Models\Property;
use App\Models\ResearchReport;
use App\Models\SubscriberWatch;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\Intelligence\IntelligenceFiles;
use App\Services\Intelligence\PublicationDesk;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

class LocalInvestorIntelligenceSeeder extends Seeder
{
    private const MARKER_SLUG = 'demo-lapsset-lamu-corridor';

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        if (DevelopmentProject::query()->where('slug', self::MARKER_SLUG)->exists()) {
            return;
        }

        $researcher = User::query()->where('email', 'research@lamuacres.test')->first()
            ?? User::query()->where('email', 'staff@lamuacres.test')->firstOrFail();
        $editor = User::query()->where('email', 'editor@lamuacres.test')->first()
            ?? User::query()->where('email', 'staff@lamuacres.test')->firstOrFail();
        $investor = User::query()->where('email', 'investor@lamuacres.test')->first();

        $county = County::query()->where('slug', 'lamu')->firstOrFail();
        $shela = Locality::query()->where('slug', 'shela')->where('county_id', $county->id)->firstOrFail();
        $hindi = Locality::query()->where('slug', 'hindi')->where('county_id', $county->id)->firstOrFail();

        $desk = app(PublicationDesk::class);
        $files = app(IntelligenceFiles::class);

        SubscriptionPlan::investorPass()->update([
            'checkout_amount' => null,
            'checkout_currency' => null,
            'checkout_policy' => null,
        ]);

        if ($investor !== null) {
            SubscriberWatch::query()->firstOrCreate([
                'user_id' => $investor->id,
                'locality_id' => $shela->id,
            ]);
        }

        $lapsset = DevelopmentProject::query()->create([
            'reference' => $desk->reference('DP', DevelopmentProject::class),
            'slug' => self::MARKER_SLUG,
            'name' => 'LAPSSET Lamu corridor (demo)',
            'category' => DevelopmentCategory::Lapsset,
            'project_status' => DevelopmentProjectStatus::UnderConstruction,
            'coverage' => 'Lamu–Manda access corridor',
            'county_id' => $county->id,
            'locality_id' => $shela->id,
            'summary' => 'Public summary of published corridor works affecting Shela access. For local development only.',
            'research_notes' => 'Premium milestone commentary: contractor staging areas may affect seasonal track access east of Shela.',
            'source_name' => 'County infrastructure bulletin (demo)',
            'source_published_on' => now()->subMonths(2),
            'verified_on' => now()->subMonth(),
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $researcher->id,
        ]);

        DevelopmentMilestone::query()->create([
            'development_project_id' => $lapsset->id,
            'title' => 'Environmental screening filed',
            'occurred_on' => now()->subMonths(3),
            'note' => 'Screening documents logged with county planning (demo record).',
            'source_name' => 'County bulletin',
            'created_by' => $researcher->id,
        ]);

        DevelopmentMilestone::query()->create([
            'development_project_id' => $lapsset->id,
            'title' => 'Access improvement contract awarded',
            'occurred_on' => now()->subMonth(),
            'note' => 'Contractor mobilisation noted in published minutes (demo).',
            'source_name' => 'County bulletin',
            'created_by' => $researcher->id,
        ]);

        $water = DevelopmentProject::query()->create([
            'reference' => $desk->reference('DP', DevelopmentProject::class),
            'slug' => 'demo-shela-water-expansion',
            'name' => 'Shela water supply expansion (demo)',
            'category' => DevelopmentCategory::Other,
            'project_status' => DevelopmentProjectStatus::Proposed,
            'coverage' => 'Shela ward',
            'county_id' => $county->id,
            'locality_id' => $shela->id,
            'summary' => 'Proposed reticulation upgrades referenced in county budget papers (demo).',
            'research_notes' => 'Premium note: verify service connections with the county water company before relying on this summary.',
            'source_name' => 'County budget extract (demo)',
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $researcher->id,
        ]);

        if ($investor !== null) {
            SubscriberWatch::query()->firstOrCreate([
                'user_id' => $investor->id,
                'development_project_id' => $lapsset->id,
            ]);
        }

        $planning = PlanningRecord::query()->create([
            'reference' => $desk->reference('PL', PlanningRecord::class),
            'slug' => 'demo-shela-settlement-planning',
            'title' => 'Shela settlement planning extract (demo)',
            'area' => 'Shela',
            'county_id' => $county->id,
            'locality_id' => $shela->id,
            'instrument_status' => PlanningInstrumentStatus::InForce,
            'permitted_uses' => 'Residential and low-rise tourism uses were listed in the published extract.',
            'restrictions' => 'Industrial processing was excluded in the extract reviewed.',
            'effective_on' => now()->subYears(2),
            'source_document' => 'County spatial plan excerpt (demo)',
            'notes' => 'Staff-held planning note for subscribers.',
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $researcher->id,
        ]);

        $shelaReport = ResearchReport::query()->create([
            'reference' => $desk->reference('RP', ResearchReport::class),
            'slug' => 'demo-shela-access-outlook',
            'title' => 'Shela coastal access outlook (demo)',
            'summary' => 'Public summary: seasonal access, infrastructure timing and buyer due-diligence prompts.',
            'body' => 'Premium analysis body for Investor Pass subscribers. Includes corridor staging assumptions and citation notes.',
            'report_date' => now()->subWeek(),
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $researcher->id,
        ]);
        $shelaReport->localities()->sync([$shela->id]);
        $shelaReport->projects()->sync([$lapsset->id]);
        $files->store(
            $shelaReport,
            $editor,
            UploadedFile::fake()->create('shela-access-demo.pdf', 64, 'application/pdf'),
            'report',
        );

        $hindiReport = ResearchReport::query()->create([
            'reference' => $desk->reference('RP', ResearchReport::class),
            'slug' => 'demo-hindi-agricultural-fringe',
            'title' => 'Hindi agricultural fringe observations (demo)',
            'summary' => 'Public summary of asking-price context and access notes for Hindi fringe parcels.',
            'body' => 'Premium Hindi fringe commentary for subscribers.',
            'report_date' => now()->subWeeks(2),
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $researcher->id,
        ]);
        $hindiReport->localities()->sync([$hindi->id]);

        $beforeScene = ImageryScene::query()->create([
            'reference' => $desk->reference('IM', ImageryScene::class),
            'provider' => 'Licensed archive (demo)',
            'acquired_on' => now()->subYears(6)->startOfYear(),
            'resolution' => '10 m',
            'coverage' => 'Shela foreshore',
            'cloud_conditions' => 'Clear',
            'license_name' => 'Local development licence',
            'redistribution_allowed' => true,
            'source_url' => 'https://example.com/demo-scene-not-fetched',
            'county_id' => $county->id,
            'locality_id' => $shela->id,
            'development_project_id' => $lapsset->id,
            'notes' => 'Before scene for comparison demo.',
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $researcher->id,
        ]);
        $files->store($beforeScene, $editor, UploadedFile::fake()->image('demo-before.jpg'), 'scene');

        $afterScene = ImageryScene::query()->create([
            'reference' => $desk->reference('IM', ImageryScene::class),
            'provider' => 'Licensed archive (demo)',
            'acquired_on' => now()->subYear()->startOfYear(),
            'resolution' => '10 m',
            'coverage' => 'Shela foreshore',
            'cloud_conditions' => 'Partly cloudy',
            'license_name' => 'Local development licence',
            'redistribution_allowed' => true,
            'source_url' => 'https://example.com/demo-scene-not-fetched-2',
            'county_id' => $county->id,
            'locality_id' => $shela->id,
            'development_project_id' => $lapsset->id,
            'notes' => 'After scene for comparison demo.',
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $researcher->id,
        ]);
        $files->store($afterScene, $editor, UploadedFile::fake()->image('demo-after.jpg'), 'scene');

        $comparison = ImageryComparison::query()->create([
            'reference' => $desk->reference('CP', ImageryComparison::class),
            'slug' => 'demo-shela-foreshore-change',
            'area_label' => 'Shela foreshore change (demo)',
            'before_scene_id' => $beforeScene->id,
            'after_scene_id' => $afterScene->id,
            'development_project_id' => $lapsset->id,
            'observed_change' => 'Visible track improvements and cleared staging near the foreshore in the licensed scenes.',
            'annotation' => 'Demo annotation for subscribers.',
            'publication_status' => PublicationStatus::Draft,
            'created_by' => $researcher->id,
        ]);

        foreach ([
            ['title' => 'Demo Shela freehold — ridge plot', 'price' => 4_500_000, 'acreage' => 0.75],
            ['title' => 'Demo Shela freehold — dune fringe', 'price' => 6_200_000, 'acreage' => 1.1],
            ['title' => 'Demo Shela freehold — access road frontage', 'price' => 8_900_000, 'acreage' => 1.5],
        ] as $listing) {
            Property::factory()->published()->listedIn($shela)->create([
                'title' => $listing['title'],
                'price_amount' => $listing['price'],
                'acreage' => $listing['acreage'],
                'summary' => 'Published demo listing for Investor Opportunities and price observations.',
            ]);
        }

        Property::factory()->published()->listedIn($hindi)->create([
            'title' => 'Demo Hindi agricultural fringe',
            'price_amount' => 2_800_000,
            'acreage' => 2.5,
            'summary' => 'Second locality demo listing for opportunities browse.',
        ]);

        foreach ([$beforeScene, $afterScene] as $scene) {
            $this->release($desk, $scene, $researcher, $editor);
        }

        foreach ([$lapsset, $water, $planning, $shelaReport, $hindiReport, $comparison] as $subject) {
            $this->release($desk, $subject, $researcher, $editor);
        }

        $this->command?->info('Local investor intelligence demo seeded.');
        $this->command?->info('Staff create pages: /staff/projects/create · /staff/reports/create · /staff/planning/create · /staff/imagery/scenes/create');
        $this->command?->info('Sign in: research@lamuacres.test (draft) · editor@lamuacres.test (publish) · investor@lamuacres.test (subscriber)');
    }

    private function release(PublicationDesk $desk, Model $subject, User $researcher, User $editor): void
    {
        if ($subject->publication_status === PublicationStatus::Draft) {
            $desk->submit($subject, $researcher);
            $subject->refresh();
        }

        if ($subject->publication_status === PublicationStatus::Review) {
            $desk->publish($subject, $editor);
        }
    }
}
