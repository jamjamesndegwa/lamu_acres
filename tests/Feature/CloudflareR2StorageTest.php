<?php

namespace Tests\Feature;

use App\Enums\OwnershipDocumentType;
use App\Models\County;
use App\Models\Locality;
use App\Models\OwnershipDocument;
use App\Models\Property;
use App\Models\User;
use App\Services\Catalog\PublicListing;
use App\Services\Media\PropertyMediaLibrary;
use App\Services\Seller\SellerPortal;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class CloudflareR2StorageTest extends FoundationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.disks.r2.bucket' => 'test-r2-public',
            'filesystems.disks.r2.url' => 'https://cdn.lamuacres.co.ke',
            'filesystems.disks.r2_private.bucket' => 'test-r2-private',
        ]);

        Storage::fake('r2');
        Storage::fake('r2_private');
        Storage::fake('local');
    }

    public function test_photo_upload_stores_original_on_r2_private_and_derivative_on_r2(): void
    {
        if (! function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD required');
        }

        $seller = User::factory()->withRoles('seller')->create();
        $locality = $this->locality();
        $property = Property::factory()->listedIn($locality)->create([
            'submitted_by' => $seller->id,
            'title' => 'Beachfront Plot',
        ]);

        $photo = UploadedFile::fake()->image('beach.jpg', 100, 100);

        $media = app(PropertyMediaLibrary::class)->storePhoto($seller, $property, $photo);

        $this->assertSame('r2_private', $media->disk);
        $this->assertSame('r2', $media->public_disk);
        $this->assertNotNull($media->size_bytes);
        $this->assertTrue($media->is_cover);

        Storage::disk('r2_private')->assertExists($media->path);
        Storage::disk('r2')->assertExists($media->public_path);
    }

    public function test_public_listing_uses_cloudflare_r2_public_url(): void
    {
        if (! function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD required');
        }

        $seller = User::factory()->withRoles('seller')->create();
        $locality = $this->locality();
        $property = Property::factory()->published()->listedIn($locality)->create([
            'submitted_by' => $seller->id,
            'title' => 'Shela Palm Grove',
        ]);

        $photo = UploadedFile::fake()->image('palm.jpg', 50, 50);
        $media = app(PropertyMediaLibrary::class)->storePhoto($seller, $property, $photo);

        $listing = app(PublicListing::class)->present($property);

        $this->assertCount(1, $listing['photos']);
        $this->assertStringStartsWith('https://cdn.lamuacres.co.ke/property-derivatives/', $listing['photos'][0]['url']);
    }

    public function test_private_ownership_document_upload_and_authorized_temporary_url(): void
    {
        $seller = User::factory()->withRoles('seller')->create();
        $other = User::factory()->withRoles('seller')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.verification')->create();

        $locality = $this->locality();
        $property = Property::factory()->listedIn($locality)->create([
            'submitted_by' => $seller->id,
        ]);

        $docFile = UploadedFile::fake()->create('title-deed.pdf', 100, 'application/pdf');

        $document = app(SellerPortal::class)->storeDocument(
            $property,
            $seller,
            $docFile,
            OwnershipDocumentType::TitleDeed
        );

        $this->assertSame('r2_private', $document->disk);
        $this->assertSame('application/pdf', $document->mime);
        $this->assertNotNull($document->size_bytes);
        Storage::disk('r2_private')->assertExists($document->path);

        // 1. Unauthorized user is denied
        $this->actingAs($other)
            ->get(route('seller.documents.download', $document))
            ->assertForbidden();

        // 2. Authorized owner is redirected to signed temporary URL
        $response = $this->actingAs($seller)
            ->get(route('seller.documents.download', $document));

        $response->assertRedirect();
        $this->assertStringContainsString($document->path, $response->headers->get('Location'));

        // 3. Authorized staff is redirected to signed temporary URL
        $staffResponse = $this->actingAs($staff)
            ->get(route('staff.ownership-documents.download', $document));

        $staffResponse->assertRedirect();
    }

    public function test_photo_deletion_removes_both_original_and_derivative_from_r2(): void
    {
        if (! function_exists('imagecreatefromstring')) {
            $this->markTestSkipped('GD required');
        }

        $seller = User::factory()->withRoles('seller')->create();
        $locality = $this->locality();
        $property = Property::factory()->listedIn($locality)->create([
            'submitted_by' => $seller->id,
        ]);

        $photo = UploadedFile::fake()->image('plot.jpg', 50, 50);
        $media = app(PropertyMediaLibrary::class)->storePhoto($seller, $property, $photo);

        Storage::disk('r2_private')->assertExists($media->path);
        Storage::disk('r2')->assertExists($media->public_path);

        app(PropertyMediaLibrary::class)->delete($media);

        Storage::disk('r2_private')->assertMissing($media->path);
        Storage::disk('r2')->assertMissing($media->public_path);
        $this->assertDatabaseMissing('property_media', ['id' => $media->id]);
    }

    public function test_resumable_idempotent_migration_command(): void
    {
        $seller = User::factory()->withRoles('seller')->create();
        $locality = $this->locality();
        $property = Property::factory()->listedIn($locality)->create([
            'submitted_by' => $seller->id,
        ]);

        // Place legacy files on local disk
        $localPath = "ownership/{$property->id}/legacy_deed.pdf";
        Storage::disk('local')->put($localPath, 'PDF FILE CONTENT');

        $doc = OwnershipDocument::query()->create([
            'property_id' => $property->id,
            'user_id' => $seller->id,
            'original_name' => 'legacy_deed.pdf',
            'path' => $localPath,
            'disk' => 'local',
            'mime' => 'application/pdf',
        ]);

        // Run dry-run
        $this->artisan('storage:migrate-r2 --dry-run')
            ->expectsOutputToContain('Starting Cloudflare R2 storage migration [DRY-RUN]')
            ->assertSuccessful();

        $this->assertSame('local', $doc->fresh()->disk);
        Storage::disk('r2_private')->assertMissing($localPath);

        // Run actual migration
        $this->artisan('storage:migrate-r2')
            ->assertSuccessful();

        $this->assertSame('r2_private', $doc->fresh()->disk);
        Storage::disk('r2_private')->assertExists($localPath);
        $this->assertSame('PDF FILE CONTENT', Storage::disk('r2_private')->get($localPath));

        // Re-run migration: idempotency test (should skip already migrated)
        $this->artisan('storage:migrate-r2')
            ->assertSuccessful();
    }

    private function locality(): Locality
    {
        $county = County::query()->firstOrCreate(
            ['slug' => 'lamu'],
            ['name' => 'Lamu', 'is_active' => true]
        );

        return Locality::query()->firstOrCreate(
            ['slug' => 'shela', 'county_id' => $county->id],
            ['name' => 'Shela', 'kind' => 'place', 'is_active' => true]
        );
    }
}
