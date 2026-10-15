<?php

namespace Tests\Feature\Verification;

use App\Enums\EvidenceSensitivity;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\VerificationCategory;
use App\Enums\VerificationItemState;
use App\Models\EvidenceAccessLog;
use App\Models\EvidenceFile;
use App\Models\Property;
use App\Models\User;
use App\Services\Verification\VerificationDesk;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Foundation\FoundationTestCase;

class EvidenceAccessTest extends FoundationTestCase
{
    public function test_document_release_is_time_limited_and_private(): void
    {
        $seller = User::factory()->withRoles('seller')->create();
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.verification')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $intruder = User::factory()->withRoles('buyer')->create();

        $this->actingAs($seller)->post(route('seller.properties.store'), ['title' => 'Manda plot', 'purpose' => 'sale', 'relationship' => 'owner']);
        $property = Property::query()->where('title', 'Manda plot')->first();
        $property->forceFill([
            'parcel_identifier' => 'LAMU-PARCEL-999',
            'status' => PropertyStatus::Published,
            'published_at' => now(),
            'availability' => PropertyAvailability::Available,
        ])->save();

        $item = $property->verificationItems()->where('category', VerificationCategory::TitleOwnership)->first();
        $this->actingAs($staff)->post(route('staff.verification.evidence', $item), [
            'file' => UploadedFile::fake()->create('national-id.pdf', 20, 'application/pdf'),
            'sensitivity' => EvidenceSensitivity::OwnerId->value,
        ]);

        $file = EvidenceFile::query()->first();
        $grant = app(VerificationDesk::class)->grant($file, $buyer, $staff, now()->addHour());
        $url = app(VerificationDesk::class)->signedLink($file, $grant);

        $this->actingAs($seller)
            ->put(route('staff.verification.review', $item), [
                'state' => VerificationItemState::Verified->value,
                'evidence_note' => 'The seller cannot verify their own title.',
            ])
            ->assertForbidden();
        $this->assertSame(VerificationItemState::Received, $item->refresh()->state);

        $this->actingAs($buyer)->get($url)->assertOk();
        $this->assertDatabaseHas('evidence_access_logs', ['evidence_file_id' => $file->id, 'user_id' => $buyer->id, 'outcome' => 'allowed']);

        $this->actingAs($intruder)->get($url)->assertForbidden();
        $this->assertSame('refused', EvidenceAccessLog::query()->where('user_id', $intruder->id)->value('outcome'));

        $this->actingAs($staff)->get($url)->assertOk();

        $grant->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->actingAs($buyer)->get($url)->assertForbidden();
        $this->actingAs($staff)->get($url)->assertOk();

        $this->get(route('land.item', $property))
            ->assertOk()
            ->assertDontSee('LAMU-PARCEL-999')
            ->assertDontSee('national-id.pdf')
            ->assertDontSee($file->path)
            ->assertSee('Level 0 — Advertised')
            ->assertSee('Outstanding');
    }
}
