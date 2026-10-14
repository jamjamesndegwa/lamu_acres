<?php

namespace Database\Factories;

use App\Enums\EnquiryStatus;
use App\Enums\LeadStatus;
use App\Models\Enquiry;
use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    protected $model = Enquiry::class;

    public function definition(): array
    {
        return [
            'buyer_id' => User::factory(),
            'property_id' => Property::factory()->published(),
            'assigned_to' => null,
            'broker_introduction_id' => null,
            'status' => EnquiryStatus::New,
            'lead_status' => LeadStatus::New,
            'message' => fake()->paragraph(),
            'referrer_url' => null,
            'campaign' => null,
            'source' => 'website',
            'ip_address' => fake()->ipv4(),
            'user_agent' => 'PHPUnit',
            'flagged_as_spam' => false,
        ];
    }
}
