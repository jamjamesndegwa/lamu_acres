<?php

namespace Database\Seeders;

use App\Enums\LocalityKind;
use App\Models\County;
use App\Models\Locality;
use App\Models\LocalityAlias;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GeographySeeder extends Seeder
{
    /**
     * Named points are approximate public references for search.
     * Boundary geometry is left unset because no verified ward file is shipped.
     */
    public function run(): void
    {
        $county = County::query()->updateOrCreate(
            ['slug' => 'lamu'],
            [
                'name' => 'Lamu',
                'code' => '005',
                'latitude' => -2.2700000,
                'longitude' => 40.9000000,
                'is_active' => true,
            ],
        );

        $west = $this->place($county, null, 'Lamu West', 'lamu-west', LocalityKind::SubCounty, null, null);
        $east = $this->place($county, null, 'Lamu East', 'lamu-east', LocalityKind::SubCounty, null, null);

        $shella = $this->place($county, $west, 'Shela', 'shela', LocalityKind::Ward, -2.2900000, 40.9100000);
        $this->place($county, $west, 'Mkomani', 'mkomani', LocalityKind::Ward, null, null);
        $hindi = $this->place($county, $west, 'Hindi', 'hindi', LocalityKind::Ward, -2.1600000, 40.8000000);
        $this->place($county, $west, 'Mkunumbi', 'mkunumbi', LocalityKind::Ward, null, null);
        $this->place($county, $west, 'Hongwe', 'hongwe', LocalityKind::Ward, null, null);
        $bahari = $this->place($county, $west, 'Bahari', 'bahari', LocalityKind::Ward, null, null);
        $this->place($county, $west, 'Witu', 'witu', LocalityKind::Ward, -2.3900000, 40.4500000);

        $this->place($county, $east, 'Faza', 'faza', LocalityKind::Ward, -2.0600000, 41.1100000);
        $this->place($county, $east, 'Kiunga', 'kiunga', LocalityKind::Ward, -1.7500000, 41.4900000);
        $this->place($county, $east, 'Basuba', 'basuba', LocalityKind::Ward, null, null);

        $island = $this->place($county, $west, 'Lamu Island', 'lamu-island', LocalityKind::Place, -2.2700000, 40.9000000);
        $this->place($county, $west, 'Manda', 'manda', LocalityKind::Place, -2.2600000, 40.9700000);
        $this->place($county, $west, 'Mokowe', 'mokowe', LocalityKind::Place, -2.2300000, 40.8500000);
        $this->place($county, $bahari, 'Mpeketoni', 'mpeketoni', LocalityKind::Place, -2.3900000, 40.7000000);
        $this->place($county, $east, 'Pate', 'pate', LocalityKind::Place, -2.1000000, 41.0500000);

        $this->alias($shella, 'Shella');
        $this->alias($hindi, 'Hindi town');
        $this->alias($island, 'Amu');
    }

    private function place(County $county, ?Locality $parent, string $name, string $slug, LocalityKind $kind, ?float $latitude, ?float $longitude): Locality
    {
        $locality = Locality::query()->firstOrNew([
            'county_id' => $county->id,
            'slug' => $slug,
        ]);

        $locality->fill([
            'parent_id' => $parent?->id,
            'name' => $name,
            'kind' => $kind,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'is_active' => true,
        ]);

        if (! $locality->exists) {
            $locality->boundary_wkt = null;
        }

        $locality->save();

        return $locality;
    }

    private function alias(Locality $locality, string $name): void
    {
        LocalityAlias::query()->updateOrCreate(
            ['locality_id' => $locality->id, 'slug' => Str::slug($name)],
            ['name' => $name],
        );
    }
}
