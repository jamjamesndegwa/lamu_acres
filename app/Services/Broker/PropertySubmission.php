<?php

namespace App\Services\Broker;

use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Models\Locality;
use App\Models\Property;
use App\Models\PropertyAudit;
use App\Models\PropertyInformationRequest;
use App\Models\PropertyMedia;
use App\Models\User;
use App\Notifications\PropertyInformationRequestedNotification;
use App\Notifications\PropertySubmittedNotification;
use App\Services\Access\CapabilityReviewer;
use App\Services\Inventory\PropertyInventory;
use App\Services\Media\PropertyMediaLibrary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertySubmission
{
    public function __construct(private DuplicateListingDetector $duplicates) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, UploadedFile>  $photos
     */
    public function submit(User $broker, array $data, array $photos, UploadedFile $authority): Property
    {
        app(CapabilityReviewer::class)->assertBrokerCanAct($broker);
        $locality = Locality::query()->with('county')->findOrFail($data['locality_id']);
        $title = trim((string) $data['title']);

        $property = Property::query()->create([
            'reference' => $this->reference(),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'title' => $title,
            'summary' => $data['summary'],
            'description' => $data['description'] ?? null,
            'county_id' => $locality->county_id,
            'locality_id' => $locality->id,
            'county' => $locality->county->name,
            'locality' => $locality->name,
            'acreage' => $data['acreage'],
            'price_amount' => $data['price_amount'],
            'price_currency' => 'KES',
            'purpose' => $data['purpose'],
            'property_type' => $data['property_type'],
            'land_use' => $data['land_use'] ?? null,
            'parcel_identifier' => $this->duplicates->normalizeParcel($data['parcel_identifier'] ?? null),
            'marketing_role' => $data['marketing_role'],
            'seller_name' => $data['seller_name'],
            'seller_contact' => $data['seller_contact'],
            'submitted_by' => $broker->id,
            'represented_by' => $broker->id,
            'availability' => PropertyAvailability::Unavailable,
            'status' => PropertyStatus::Review,
            'published_at' => null,
            'featured' => false,
        ]);

        foreach ($photos as $photo) {
            $this->storeMedia($property, $broker, $photo, 'photo');
        }

        $this->storeMedia($property, $broker, $authority, 'authority');

        PropertyAudit::query()->create([
            'property_id' => $property->id,
            'actor_id' => $broker->id,
            'action' => 'submitted',
            'to_status' => PropertyStatus::Review->value,
            'changes' => ['duplicate_warnings' => $this->duplicates->warnings(
                $property->parcel_identifier,
                $property->locality_id,
                $property->acreage,
                $property->id,
            )],
        ]);

        Notification::send(
            User::permission('staff.properties')->get(),
            new PropertySubmittedNotification($property),
        );

        return $property;
    }

    public function requestInformation(User $staff, Property $property, string $question): PropertyInformationRequest
    {
        $request = $property->informationRequests()->create([
            'staff_id' => $staff->id,
            'question' => trim($question),
        ]);

        PropertyAudit::query()->create([
            'property_id' => $property->id,
            'actor_id' => $staff->id,
            'action' => 'information_requested',
            'from_status' => $property->status->value,
            'to_status' => $property->status->value,
        ]);

        $property->submitter?->notify(new PropertyInformationRequestedNotification($request));

        return $request;
    }

    public function respond(User $broker, PropertyInformationRequest $request, string $response, string $message = 'A broker replied to a request for information.'): void
    {
        if ($request->responded_at !== null && $request->response === trim($response)) {
            return;
        }
        $request->load('property');

        $request->update([
            'response' => trim($response),
            'responded_at' => now(),
        ]);

        PropertyAudit::query()->create([
            'property_id' => $request->property_id,
            'actor_id' => $broker->id,
            'action' => 'information_provided',
            'from_status' => $request->property->status->value,
            'to_status' => $request->property->status->value,
        ]);

        Notification::send(
            User::permission('staff.properties')->get(),
            new PropertySubmittedNotification($request->property, $message),
        );
    }

    public function approve(User $staff, Property $property): Property
    {
        $from = $property->status;

        if ($from === PropertyStatus::Published) {
            return $property;
        }

        app(PropertyInventory::class)->publish($staff, $property);

        PropertyAudit::query()->create([
            'property_id' => $property->id,
            'actor_id' => $staff->id,
            'action' => 'approved',
            'from_status' => $from->value,
            'to_status' => PropertyStatus::Published->value,
        ]);

        return $property;
    }

    private function storeMedia(Property $property, User $broker, UploadedFile $file, string $kind): PropertyMedia
    {
        if ($kind === 'photo') {
            return app(PropertyMediaLibrary::class)->storePhoto($broker, $property, $file, 'broker-listings');
        }

        $disk = config('filesystems.disks.r2_private.bucket') || config('filesystems.disks.r2.bucket') ? 'r2_private' : 'local';
        $path = $file->store('broker-listings/'.$property->id, $disk);

        try {
            return $property->media()->create([
                'uploaded_by' => $broker->id,
                'kind' => $kind,
                'disk' => $disk,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
            ]);
        } catch (\Throwable $throwable) {
            Storage::disk($disk)->delete($path);

            throw $throwable;
        }
    }

    private function reference(): string
    {
        do {
            $reference = 'LA-'.strtoupper(Str::random(8));
        } while (Property::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
