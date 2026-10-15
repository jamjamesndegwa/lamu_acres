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
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Foundation\FoundationTestCase;

class EvidenceAccessTest extends FoundationTestCase
{
    public function test_verification_viewer_displays_and_logs_evidence_and_rejects_unsafe_inline_types(): void
    {
        Storage::fake('local');
        $staff = User::factory()->withRoles('staff')->withPermissions('staff.verification')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $property = Property::factory()->create();
        $desk = app(VerificationDesk::class);
        $desk->seed($property);
        $item = $property->verificationItems()->firstOrFail();
        $image = $desk->storeEvidence($item, $staff, UploadedFile::fake()->image('boundary.png'), EvidenceSensitivity::Parcel);
        $html = $desk->storeEvidence($item, $staff, UploadedFile::fake()->createWithContent('notes.html', '<html><script>alert(1)</script></html>'), EvidenceSensitivity::General);

        $this->actingAs($staff)->get(route('staff.verification.show', $property))
            ->assertOk()->assertSee('Document viewing section')->assertSee('boundary.png')->assertSee('notes.html')
            ->assertSee(route('staff.verification.files.preview', [$image, 'inline' => 1]), false);
        $this->actingAs($buyer)->get(route('staff.verification.files.preview', [$image, 'inline' => 1]))->assertForbidden();
        $response = $this->actingAs($staff)->get(route('staff.verification.files.preview', [$image, 'inline' => 1]));
        $response->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertSame(Storage::disk('local')->get($image->path), $response->streamedContent());
        $this->assertSame(1, $image->accessLogs()->where('user_id', $staff->id)->where('outcome', 'allowed')->count());
        $this->actingAs($staff)->get(route('staff.verification.files.preview', [$html, 'inline' => 1]))->assertUnsupportedMediaType();
        $this->actingAs($staff)->get(route('staff.verification.files.preview', $html))->assertOk()
            ->assertDownload('notes.html');
    }

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
