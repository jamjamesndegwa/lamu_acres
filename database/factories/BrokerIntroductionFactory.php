<?php

namespace Database\Factories;

use App\Enums\BrokerIntroductionStatus;
use App\Models\BrokerIntroduction;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BrokerIntroduction>
 */
class BrokerIntroductionFactory extends Factory
{
    protected $model = BrokerIntroduction::class;

    public function definition(): array
    {
        return [
            'reference' => 'BR-'.strtoupper(Str::random(8)),
            'broker_id' => User::factory(),
            'buyer_id' => User::factory(),
            'property_id' => Property::factory()->published(),
            'status' => BrokerIntroductionStatus::Active,
            'introduced_at' => now()->subDay(),
            'expires_at' => now()->addMonths(3),
        ];
    }
}
