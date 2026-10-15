<?php

namespace Tests\Feature\Media;

use App\Enums\Amenity;
use App\Enums\PropertyStatus;
use App\Models\County;
use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyAvailabilityEvent;
use App\Models\PropertyMedia;
use App\Models\PropertyPriceChange;
use App\Models\User;
use App\Services\Inventory\PropertyInventory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class PropertyMediaTest extends FoundationTestCase
{
    public function test_uploads_are_authorized_and_public_copies_drop_location_data(): void
    {
        if (! function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD is required to build public photo copies.');
        }

        Storage::fake('local');
        Storage::fake('public');

        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $owner = User::factory()->withRoles('broker')->create();
        $other = User::factory()->withRoles('broker')->create();
        $locality = $this->locality();
        $property = Property::factory()->published()->listedIn($locality)->create([
            'submitted_by' => $owner->id,
            'title' => 'Open Shela holding',
        ]);

        $this->actingAs($buyer)
            ->post(route('staff.properties.photos.store', $property), [
                'photo' => UploadedFile::fake()->image('plot.jpg'),
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('staff.properties.photos.store', $property), [
                'photo' => UploadedFile::fake()->create('plot.jpg', 6000, 'image/jpeg'),
            ])
            ->assertSessionHasErrors('photo');

        $photo = UploadedFile::fake()->image('shore.jpg', 40, 40);
        file_put_contents($photo->getPathname(), file_get_contents($photo->getPathname()).'GPSLatitude');

        $this->actingAs($staff)
            ->post(route('staff.properties.photos.store', $property), ['photo' => $photo])
            ->assertRedirect();

        $media = PropertyMedia::query()->where('property_id', $property->id)->where('kind', 'photo')->firstOrFail();

        $this->assertSame($staff->id, $media->uploaded_by);
        $this->assertTrue($media->is_cover);
        $this->assertSame('local', $media->disk);
        $this->assertSame('local', $media->public_disk);
        $this->assertStringStartsWith('property-originals/'.$property->id.'/', $media->path);
        $this->assertStringStartsWith('property-derivatives/'.$property->id.'/', $media->public_path);
        $this->assertStringContainsString('GPSLatitude', Storage::disk('local')->get($media->path));
        $this->assertStringNotContainsString('GPSLatitude', Storage::disk('local')->get($media->public_path));
        Storage::disk('public')->assertMissing($media->path);
        Storage::disk('public')->assertMissing($media->public_path);

        $this->actingAs($buyer)
            ->get(route('staff.properties.media.original', [$property, $media]))
            ->assertForbidden();

        $original = $this->actingAs($staff)->get(route('staff.properties.media.original', [$property, $media]));
        $original->assertOk();
        $this->assertStringContainsString('GPSLatitude', $original->streamedContent());

        $public = $this->get(route('land.photos.show', ['lamu', 'shela', $property, $media]));
        $public->assertOk();
        $this->assertStringContainsString('image/jpeg', (string) $public->headers->get('content-type'));
        $this->assertStringNotContainsString('GPSLatitude', $public->streamedContent());

        $media->forceFill([
            'public_disk' => 'public',
            'public_path' => 'property-derivatives/'.$property->id.'/escaped.jpg',
        ])->save();

        $this->get(route('land.photos.show', ['lamu', 'shela', $property, $media]))->assertNotFound();

        $owned = UploadedFile::fake()->image('owned.jpg', 20, 20);
        $this->actingAs($owner)
            ->post(route('broker.listings.photos.store', $property), ['photo' => $owned])
            ->assertRedirect();

        $ownedMedia = PropertyMedia::query()->where('original_name', 'owned.jpg')->firstOrFail();

        $this->actingAs($other)
            ->delete(route('broker.listings.media.destroy', [$property, $ownedMedia]))
            ->assertForbidden();

        $this->actingAs($buyer)
            ->delete(route('staff.properties.media.destroy', [$property, $ownedMedia]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->delete(route('broker.listings.media.destroy', [$property, $ownedMedia]))
            ->assertRedirect();

        $this->assertDatabaseMissing('property_media', ['id' => $ownedMedia->id]);

        $this->actingAs($staff)
            ->delete(route('staff.properties.media.destroy', [$property, $media]))
            ->assertRedirect();

        $this->assertDatabaseMissing('property_media', ['id' => $media->id]);
        Storage::disk('local')->assertMissing($media->path);
    }

    public function test_unpublished_photos_and_other_property_files_are_not_public(): void
    {
        if (! function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD is required to build public photo copies.');
        }

        Storage::fake('local');

        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $locality = $this->locality();
        $draft = Property::factory()->listedIn($locality)->create();

        $this->actingAs($staff)->post(route('staff.properties.photos.store', $draft), [
            'photo' => UploadedFile::fake()->image('draft.jpg'),
        ])->assertRedirect();

        $media = PropertyMedia::query()->where('property_id', $draft->id)->firstOrFail();

        $this->get(route('land.photos.show', ['lamu', 'shela', $draft, $media]))->assertNotFound();
    }

    public function test_video_links_cover_order_and_history_are_recorded(): void
    {
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.properties')->create();
        $locality = $this->locality();
        $inventory = app(PropertyInventory::class);
        $payload = [
            'title' => 'Mokowe holding',
            'description' => 'A recorded holding.',
            'county_id' => $locality->county_id,
            'locality_id' => $locality->id,
            'property_type' => 'agricultural',
            'land_use' => 'agricultural',
            'purpose' => 'sale',
            'acreage' => 10,
            'price_amount' => 1_000_000,
            'marketing_role' => 'owner',
        ];

        $property = $inventory->create($staff, $payload);
        $inventory->update($staff, $property, [...$payload, 'price_amount' => 1_200_000]);
        $inventory->transition($staff, $property, PropertyStatus::Review);
        $this->attachPublicPhoto($property, $staff);
        $this->clearCriticalVerification($property, $staff);
        $inventory->transition($staff, $property->refresh(), PropertyStatus::Published);

        $this->assertSame([1_000_000, 1_200_000], PropertyPriceChange::query()->where('property_id', $property->id)->orderBy('id')->pluck('price_amount')->all());
        $this->assertTrue(PropertyAvailabilityEvent::query()->where('property_id', $property->id)->where('status', 'published')->where('availability', 'available')->exists());

        $this->actingAs($staff)->post(route('staff.properties.videos.store', $property), [
            'video_url' => 'http://example.com/private.mp4',
        ])->assertSessionHasErrors('video_url');

        $this->actingAs($staff)->post(route('staff.properties.videos.store', $property), [
            'video_url' => 'https://www.youtube.com/watch?v=lamuacres01',
        ])->assertRedirect();

        $this->assertDatabaseHas('property_media', [
            'property_id' => $property->id,
            'kind' => 'video',
            'video_url' => 'https://www.youtube.com/watch?v=lamuacres01',
        ]);
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
