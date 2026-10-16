<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SystemDefaultsSeeder extends Seeder
{
    public function run(): void
    {
        SubscriptionPlan::query()->firstOrCreate(
            ['code' => SubscriptionPlan::INVESTOR_PASS],
            [
                'name' => 'Lamu Investor Pass',
                'amount_cents' => 10000,
                'currency' => 'USD',
                'interval' => 'year',
                'terms' => 'Annual access to published investor research. This application does not collect payment. A finance officer records the membership before access starts.',
                'is_active' => true,
            ],
        );
    }
}
