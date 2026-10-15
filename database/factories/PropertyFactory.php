<?php

namespace Database\Factories;

use App\Enums\LandTenure;
use App\Enums\LandUse;
use App\Enums\ListingPurpose;
use App\Enums\OwnerLinkStatus;
use App\Enums\OwnerRelationship;
use App\Enums\OwnershipStructure;
use App\Enums\PriceBasis;
use App\Enums\PropertyAvailability;
use App\Enums\PropertyStatus;
use App\Enums\PropertyType;
use App\Models\Locality;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    protected $model = Property::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'reference' => 'LA-'.strtoupper(Str::random(8)),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(5)),
            'title' => $title,
            'county' => 'Lamu',
            'locality' => fake()->randomElement(['Manda', 'Shela', 'Hindi', 'Mokowe']),
            'acreage' => fake()->randomFloat(2, 0.25, 40),
            'price_amount' => fake()->numberBetween(500_000, 25_000_000),
            'price_currency' => 'KES',
            'summary' => fake()->paragraph(),
            'purpose' => ListingPurpose::Sale,
            'property_type' => PropertyType::Agricultural,
            'availability' => PropertyAvailability::Available,
            'featured' => false,
            'status' => PropertyStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PropertyStatus::Published,
            'availability' => PropertyAvailability::Available,
            'published_at' => now()->subHour(),
        ]);
    }

    public function unavailable(): static
    {
        return $this->state(fn (): array => [
            'status' => PropertyStatus::Published,
            'availability' => PropertyAvailability::Unavailable,
            'published_at' => now()->subHour(),
        ]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => [
            'featured' => true,
        ]);
    }

    public function lease(PriceBasis $basis = PriceBasis::ParcelYear): static
    {
        return $this->state(fn (array $attributes): array => [
            'purpose' => ListingPurpose::Lease,
            'price_basis' => $basis,
            'price_rate' => $basis->isPerAcre() ? 30_000 : null,
            'price_amount' => $basis->isPerAcre()
                ? (int) round(30_000 * (float) $attributes['acreage'])
                : 240_000,
            'lease_min_months' => 12,
            'lease_max_months' => 120,
        ]);
    }

    /**
     * A complete landowner listing waiting in the staff review queue.
     */
    public function sellerListing(User $seller): static
    {
        return $this->state(fn (): array => [
            'reference' => 'SP-'.strtoupper(Str::random(8)),
            'description' => fake()->paragraph(),
            'land_use' => LandUse::Agricultural,
            'tenure' => LandTenure::Freehold,
            'ownership_structure' => OwnershipStructure::Individual,
            'parcel_identifier' => 'LAMU/'.strtoupper(Str::random(6)),
            'status' => PropertyStatus::Review,
            'availability' => PropertyAvailability::Unavailable,
            'submitted_by' => $seller->id,
            'submitted_at' => now(),
        ])->afterCreating(function (Property $property) use ($seller): void {
            $property->owners()->create([
                'user_id' => $seller->id,
                'relationship' => OwnerRelationship::Owner,
                'status' => OwnerLinkStatus::Pending,
            ]);
        });
    }

    public function listedIn(Locality $locality): static
    {
        $locality->loadMissing('county');

        return $this->state(fn (): array => [
            'county_id' => $locality->county_id,
            'locality_id' => $locality->id,
            'county' => $locality->county->name,
            'locality' => $locality->name,
        ]);
    }
}
