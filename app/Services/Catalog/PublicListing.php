<?php

namespace App\Services\Catalog;

use App\Enums\Amenity;
use App\Enums\AmenityState;
use App\Enums\DocumentCheckState;
use App\Models\Property;
use App\Models\PropertyMedia;
use App\Services\Geography\LocationDirectory;
use App\Services\Media\PropertyMediaLibrary;
use App\Services\Verification\VerificationDesk;

class PublicListing
{
    public function __construct(private LocationDirectory $locations, private VerificationDesk $verification) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Property $property): array
    {
        $property->loadMissing([
            'areaCounty:id,name,slug',
            'areaLocality:id,name,slug,county_id,latitude,longitude',
            'highlights',
            'amenityStates',
            'documentChecks',
        ]);

        $photos = [];

        if ($property->areaCounty !== null && $property->areaLocality !== null) {
            $photos = $property->media()
                ->where('kind', 'photo')
                ->whereNotNull('public_path')
                ->orderByDesc('is_cover')
                ->orderBy('position')
                ->get()
                ->filter(fn (PropertyMedia $media): bool => PropertyMediaLibrary::isPublicDerivative($property, $media))
                ->map(fn (PropertyMedia $media): array => [
                    'url' => ($media->public_disk === 'r2' && filled(config('filesystems.disks.r2.url')))
                        ? ($media->public_url ?: rtrim(config('filesystems.disks.r2.url'), '/').'/'.ltrim($media->public_path, '/'))
                        : route('land.photos.show', [$property->areaCounty, $property->areaLocality, $property, $media]),
                    'alt' => $property->title,
                    'cover' => (bool) $media->is_cover,
                ])
                ->values()
                ->all();
        }

        $videos = $property->media()
            ->where('kind', 'video')
            ->whereNotNull('video_url')
            ->orderBy('position')
            ->get()
            ->map(fn (PropertyMedia $media): array => [
                'url' => $media->video_url,
            ])
            ->all();

        $map = $this->map($property);
        $property->loadMissing(['passport', 'verificationItems']);
        $review = $this->verification->publicSummary($property);

        return [
            'title' => $property->title,
            'summary' => $property->summary,
            'description' => $property->description,
            'location' => $property->locationLabel(),
            'purpose' => $property->purpose?->label(),
            'type' => $property->property_type?->label(),
            'is_lease' => $property->isLease(),
            'acreage' => $this->acreage($property),
            'minimum_acreage' => $property->minimum_acreage !== null ? rtrim(rtrim(number_format((float) $property->minimum_acreage, 2), '0'), '.') : null,
            'price_label' => $property->isLease() ? 'Asking rent' : 'Asking price',
            'price' => $property->formattedPrice(),
            'price_basis' => $property->price_amount !== null ? $property->priceBasis()->label() : null,
            'price_negotiable' => (bool) $property->price_negotiable,
            'price_per_acre' => $property->pricePerAcre(),
            'available_from' => $property->available_from?->format('j M Y'),
            'lease_term' => $this->leaseTerm($property),
            'tenure' => $property->tenure?->label(),
            'landmark' => $property->landmark,
            'terms' => $property->publicListingTerms(),
            'availability' => $property->availability?->label() ?? 'Available',
            'status' => $property->status?->label(),
            'road' => $property->road_access?->label() ?? 'Unknown',
            'verification' => $review['level'],
            'verification_items' => $review['items'],
            'reviewed_on' => $property->last_reviewed_at?->timezone(config('app.timezone'))->format('j M Y'),
            'highlights' => $property->highlights->pluck('body')->all(),
            'amenities' => $this->amenities($property),
            'documents' => $this->documents($property),
            'infrastructure' => $property->infrastructure_note,
            'nearby' => $map === null ? [] : $this->nearby($property, $map['search_lat'], $map['search_lng']),
            'map' => $map['embed'] ?? null,
            'photos' => $photos,
            'videos' => $videos,
            'contact_name' => (string) config('lamu.desk_name'),
            'contact_email' => $this->deskEmail(),
            'schema' => $this->schema($property),
        ];
    }

    /**
     * Directory card data. Expects photos eager-loaded on the media relation.
     *
     * @return array{title: string, location: string, is_lease: bool, price: ?int, price_suffix: string, price_per_acre: ?int, acres: float, image: string, url: string}
     */
    public function card(Property $property): array
    {
        $cover = $property->areaCounty !== null && $property->areaLocality !== null
            ? $property->media->first(fn (PropertyMedia $media): bool => $media->kind === 'photo' && PropertyMediaLibrary::isPublicDerivative($property, $media))
            : null;

        return [
            'title' => $property->title,
            'location' => $property->locationLabel(),
            'is_lease' => $property->isLease(),
            'price' => $property->price_amount !== null ? (int) $property->price_amount : null,
            'price_suffix' => $property->price_amount !== null ? $property->priceBasis()->periodSuffix() : '',
            'price_per_acre' => $property->pricePerAcre(),
            'acres' => $property->offeredAcreage(),
            'image' => $cover !== null
                ? (($cover->public_disk === 'r2' && filled(config('filesystems.disks.r2.url')))
                    ? ($cover->public_url ?: rtrim(config('filesystems.disks.r2.url'), '/').'/'.ltrim($cover->public_path, '/'))
                    : route('land.photos.show', [$property->areaCounty, $property->areaLocality, $property, $cover]))
                : 'images/default_land_image.jpg',
            'url' => $property->publicUrl(),
        ];
    }

    private function leaseTerm(Property $property): ?string
    {
        if (! $property->isLease() || $property->lease_min_months === null) {
            return null;
        }

        return $property->lease_max_months !== null && $property->lease_max_months > $property->lease_min_months
            ? $property->lease_min_months.' to '.$property->lease_max_months.' months'
            : 'From '.$property->lease_min_months.' months';
    }

    private function acreage(Property $property): string
    {
        $acres = $property->offeredAcreage();

        if ($acres <= 0) {
            return 'Not stated';
        }

        return rtrim(rtrim(number_format($acres, 2), '0'), '.');
    }

    /**
     * @return array<int, array{name: string, state: string}>
     */
    private function amenities(Property $property): array
    {
        $stored = $property->amenityStates->keyBy(fn ($row) => $row->amenity->value);

        return array_map(function (Amenity $amenity) use ($stored): array {
            $state = $stored->get($amenity->value)?->state ?? AmenityState::Unknown;

            return [
                'name' => $amenity->label(),
                'state' => $state->label(),
            ];
        }, Amenity::cases());
    }

    /**
     * @return array<int, array{name: string, state: string}>
     */
    private function documents(Property $property): array
    {
        $labels = [
            'title_search' => 'Title search',
            'survey_plan' => 'Survey plan',
        ];

        return collect($labels)->map(function (string $label, string $code) use ($property): array {
            $check = $property->documentChecks->firstWhere('code', $code);

            return [
                'name' => $label,
                'state' => ($check?->state ?? DocumentCheckState::Unknown)->label(),
            ];
        })->values()->all();
    }

    /**
     * @return array{embed: string, search_lat: float, search_lng: float}|null
     */
    private function map(Property $property): ?array
    {
        if ($property->public_latitude === null || $property->public_longitude === null) {
            return null;
        }

        $lat = round((float) $property->public_latitude, 2);
        $lng = round((float) $property->public_longitude, 2);
        $span = 0.08;
        $bbox = implode(',', [
            $lng - $span,
            $lat - $span,
            $lng + $span,
            $lat + $span,
        ]);

        return [
            'embed' => 'https://www.openstreetmap.org/export/embed.html?bbox='.$bbox.'&layer=mapnik&marker='.$lat.','.$lng,
            'search_lat' => (float) $property->public_latitude,
            'search_lng' => (float) $property->public_longitude,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function nearby(Property $property, float $latitude, float $longitude): array
    {
        return $this->locations->nearby($latitude, $longitude, 20)
            ->reject(fn ($locality): bool => (int) $locality->id === (int) $property->locality_id)
            ->take(5)
            ->map(fn ($locality): string => $locality->name)
            ->values()
            ->all();
    }

    private function deskEmail(): ?string
    {
        $email = config('lamu.desk_email');

        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function schema(Property $property): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'RealEstateListing',
            'name' => $property->title,
            'url' => $property->publicUrl(),
            'description' => $property->summary ?: $property->description,
        ];

        if ($property->price_amount !== null) {
            $schema['offers'] = [
                '@type' => 'Offer',
                'price' => (int) $property->price_amount,
                'priceCurrency' => $property->price_currency ?: 'KES',
                'availability' => 'https://schema.org/InStock',
                'businessFunction' => $property->isLease()
                    ? 'http://purl.org/goodrelations/v1#LeaseOut'
                    : 'http://purl.org/goodrelations/v1#Sell',
            ];

            $period = $property->priceBasis()->periodSuffix();

            if ($property->isLease() && $period !== '') {
                $schema['offers']['priceSpecification'] = [
                    '@type' => 'UnitPriceSpecification',
                    'price' => (int) $property->price_amount,
                    'priceCurrency' => $property->price_currency ?: 'KES',
                    'unitText' => $period === 'per month' ? 'MONTH' : 'ANN',
                ];
            }
        }

        return $schema;
    }
}
