<?php

namespace Database\Factories;

use App\Models\Enquiry;
use App\Models\EnquiryResponse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnquiryResponse>
 */
class EnquiryResponseFactory extends Factory
{
    protected $model = EnquiryResponse::class;

    public function definition(): array
    {
        return [
            'enquiry_id' => Enquiry::factory(),
            'user_id' => User::factory(),
            'body' => fake()->paragraph(),
            'visible_to_buyer' => true,
        ];
    }
}
