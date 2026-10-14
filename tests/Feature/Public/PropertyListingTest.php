<?php

namespace Tests\Feature\Public;

use App\Enums\Amenity;
use App\Enums\PropertyStatus;
use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Enums\VerificationLevel;
use App\Enums\VerificationOverride;
use App\Models\County;
use App\Models\ListingDocumentRequest;
use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class PropertyListingTest extends FoundationTestCase
{
    public function test_public_listing_shows_price_and_status_without_private_records(): void
    {
        if (! function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD is required to build public photo copies.');
        }

        Storage::fake('local');
        config(['lamu.desk_email' => 'desk@lamuacres.test']);

        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $shela = $this->place('shela', 'Shela', null, null);
        $this->place('manda-road', 'Manda road note', -2.2701000, 40.9010000);

        $listing = Property::factory()->published()->listedIn($shela)->create([
            'title' => 'Open Shela listing',
            'summary' => 'A published summary.',
            'description' => 'A public description of the holding.',
            'acreage' => 5,
            'price_amount' => 2_500_000,
            'seller_name' => 'Hidden Owner Njoroge',
            'seller_contact' => '0700111222',
            'parcel_identifier' => 'PARCEL-SECRET-99',
            'restricted_latitude' => 9.8765432,
            'restricted_longitude' => 40.1234567,
            'public_latitude' => -2.2698765,
            'public_longitude' => 40.9012345,
        ]);

        $listing->media()->create([
            'uploaded_by' => $staff->id,
            'kind' => 'authority',
            'disk' => 'local',
            'path' => 'broker-listings/'.$listing->id.'/secret-title.pdf',
            'original_name' => 'secret-title.pdf',
            'mime' => 'application/pdf',
        ]);

        $photo = UploadedFile::fake()->image('shore.jpg', 30, 30);
        file_put_contents($photo->getPathname(), file_get_contents($photo->getPathname()).'GPSLatitude');

        $this->actingAs($staff)->post(route('staff.properties.photos.store', $listing), [
            'photo' => $photo,
        ])->assertRedirect();

        $media = PropertyMedia::query()->where('property_id', $listing->id)->where('kind', 'photo')->firstOrFail();

        $this->actingAs($staff)->post(route('staff.properties.videos.store', $listing), [
            'video_url' => 'https://www.youtube.com/watch?v=lamuacres01',
        ])->assertRedirect();

        $this->actingAs($staff)->put(route('staff.properties.details.update', $listing), $this->details())->assertRedirect();

        $listing->passport()->create([
            'level' => VerificationLevel::OwnershipChecked,
            'override' => VerificationOverride::None,
        ]);
        $listing->verificationItems()->create([
            'category' => VerificationCategory::TitleOwnership,
            'state' => VerificationItemState::Verified,
            'notes' => 'SECRET-TITLE-NOTE',
            'evidence_note' => 'SECRET-EVIDENCE-NOTE',
        ]);
        $listing->forceFill(['last_reviewed_at' => now()])->save();

        $page = $this->get(route('land.show', ['lamu', 'shela', $listing]));

        $page->assertOk()
            ->assertSee('Open Shela listing')
            ->assertSee('A published summary.')
            ->assertSee('A public description of the holding.')
            ->assertSee('Available')
            ->assertSee('Published')
            ->assertSee('KSh 2,500,000')
            ->assertSee('5 acres')
            ->assertSee('KSh 500,000')
            ->assertSee('Quiet shoreline')
            ->assertSee('Tarmac road')
            ->assertSee('Verified')
            ->assertSee('Level 2 — Ownership and authority checked')
            ->assertSee('Title and ownership')
            ->assertSee(now()->format('j M Y'))
            ->assertDontSee('SECRET-TITLE-NOTE')
            ->assertDontSee('SECRET-EVIDENCE-NOTE')
            ->assertSee('Public jetty is recorded nearby')
            ->assertSee('Manda road note')
            ->assertSee('Recorded on file')
            ->assertSee('Not published')
            ->assertSee('https://www.youtube.com/watch?v=lamuacres01')
            ->assertSee('desk@lamuacres.test')
            ->assertSee(route('land.photos.show', ['lamu', 'shela', $listing, $media], false), false)
            ->assertSee('application/ld+json', false)
            ->assertDontSee('GPSLatitude')
            ->assertDontSee('secret-title.pdf')
            ->assertDontSee('broker-listings')
            ->assertDontSee('property-originals')
            ->assertDontSee('property-derivatives')
            ->assertDontSee('/storage/')
            ->assertDontSee('Hidden Owner Njoroge')
            ->assertDontSee('0700111222')
            ->assertDontSee('PARCEL-SECRET-99')
            ->assertDontSee('9.8765432')
            ->assertDontSee('40.1234567')
            ->assertDontSee('-2.2698765')
            ->assertDontSee('-2.2701000')
            ->assertDontSee('latitude')
            ->assertDontSee('longitude')
            ->assertDontSee('Seller');

        $draft = Property::factory()->listedIn($shela)->create(['title' => 'Closed draft listing']);
        $this->get(route('land.show', ['lamu', 'shela', $draft]))->assertNotFound();

        $reserved = Property::factory()->listedIn($shela)->create([
            'title' => 'Reserved holding',
            'status' => PropertyStatus::Reserved,
            'availability' => 'unavailable',
            'price_amount' => 9_999_999,
        ]);
        $this->get(route('land.show', ['lamu', 'shela', $reserved]))->assertNotFound();

        $this->actingAs($buyer)->post(route('land.visits.store', $listing), [
            'preferred_on' => now()->addDay()->toDateString(),
            'note' => 'Morning visit',
        ])->assertRedirect(route('land.show', ['lamu', 'shela', $listing]));

        $this->assertDatabaseHas('property_visit_requests', [
            'property_id' => $listing->id,
            'buyer_id' => $buyer->id,
            'note' => 'Morning visit',
        ]);

        $this->actingAs($buyer)->post(route('land.documents.store', $listing), [
            'note' => 'Please confirm the checklist',
        ])->assertRedirect()
            ->assertSessionHas('status', 'Document request sent. Original files are not included in this response.');

        $this->assertTrue(ListingDocumentRequest::query()->where('property_id', $listing->id)->where('buyer_id', $buyer->id)->exists());

        $this->actingAs($buyer)
            ->get(route('land.show', ['lamu', 'shela', $listing]))
            ->assertOk()
            ->assertDontSee('secret-title.pdf')
            ->assertDontSee('PARCEL-SECRET-99');
    }

    /**
     * @return array<string, mixed>
     */
    private function details(): array
    {
        $amenities = [];

        foreach (Amenity::cases() as $amenity) {
            $amenities[$amenity->value] = 'unknown';
        }

        $amenities['water'] = 'verified';

        return [
            'road_access' => 'tarmac',
            'infrastructure_note' => 'Public jetty is recorded nearby',
            'highlights' => "Quiet shoreline\nExisting well",
            'amenities' => $amenities,
            'documents' => [
                'title_search' => 'listed',
                'survey_plan' => 'withheld',
            ],
        ];
    }

    private function place(string $slug, string $name, ?float $latitude, ?float $longitude): Locality
    {
        $county = County::query()->firstOrCreate(
            ['slug' => 'lamu'],
            ['name' => 'Lamu', 'is_active' => true],
        );

        return Locality::query()->create([
            'county_id' => $county->id,
            'name' => $name,
            'slug' => $slug,
            'kind' => 'place',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'is_active' => true,
        ]);
    }
}
