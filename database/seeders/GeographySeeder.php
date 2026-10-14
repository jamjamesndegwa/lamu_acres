<?php

namespace Database\Seeders;

use App\Models\County;
use App\Models\Locality;
use Illuminate\Database\Seeder;

class GeographySeeder extends Seeder
{
    public function run(): void
    {
        $county = County::query()->updateOrCreate(
            ['slug' => 'lamu'],
            ['name' => 'Lamu'],
        );

        $localities = [
            'lamu-island' => 'Lamu Island',
            'shela' => 'Shela',
            'manda' => 'Manda',
            'mokowe' => 'Mokowe',
            'hindi' => 'Hindi',
            'mpeketoni' => 'Mpeketoni',
            'witu' => 'Witu',
            'faza' => 'Faza',
            'pate' => 'Pate',
            'kiunga' => 'Kiunga',
        ];

        foreach ($localities as $slug => $name) {
            Locality::query()->updateOrCreate(
                ['county_id' => $county->id, 'slug' => $slug],
                ['name' => $name],
            );
        }
    }
}
