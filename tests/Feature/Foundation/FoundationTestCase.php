<?php

namespace Tests\Feature\Foundation;

use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Models\User;
use App\Services\Verification\VerificationDesk;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class FoundationTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AccessSeeder::class);
    }

    /**
     * Record a staff outcome on every critical passport check so the passport no longer blocks publication.
     */
    protected function clearCriticalVerification(Property $property, User $staff): void
    {
        $desk = app(VerificationDesk::class);
        $desk->seed($property);

        foreach (VerificationCategory::cases() as $category) {
            if (! $category->critical()) {
                continue;
            }

            $desk->review($property->verificationItems()->where('category', $category)->firstOrFail(), $staff, [
                'state' => VerificationItemState::NotApplicable->value,
                'evidence_note' => 'Not applicable for this listing.',
            ]);
        }

        $property->unsetRelation('verificationItems')->unsetRelation('passport');
    }

    /**
     * Attach a photo record whose public copy lives in the property's derivative folder.
     */
    protected function attachPublicPhoto(Property $property, User $uploader): PropertyMedia
    {
        $media = $property->media()->create([
            'uploaded_by' => $uploader->id,
            'kind' => 'photo',
            'disk' => 'local',
            'path' => 'property-originals/'.$property->id.'/original.jpg',
            'public_disk' => 'local',
            'public_path' => 'property-derivatives/'.$property->id.'/public.jpg',
            'original_name' => 'plot.jpg',
            'mime' => 'image/jpeg',
            'position' => 1,
            'is_cover' => true,
        ]);

        $property->unsetRelation('media');

        return $media;
    }
}
