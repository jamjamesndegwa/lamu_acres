<?php

namespace Tests\Feature\Account;

use App\Models\Property;
use App\Models\User;
use Tests\Feature\Foundation\FoundationTestCase;

class ListingReturnTest extends FoundationTestCase
{
    public function test_buyer_registration_returns_to_the_listing_the_buyer_started_from(): void
    {
        $property = Property::factory()->published()->create();
        $listingUrl = $property->publicUrl();

        $this->get(route('register', ['return_to' => $listingUrl]))
            ->assertOk()
            ->assertSee('return_to=', false);

        $response = $this->post(route('register'), [
            'name' => 'Lamu Buyer',
            'email' => 'listing-buyer@example.test',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ]);

        $buyer = User::query()->where('email', 'listing-buyer@example.test')->firstOrFail();

        $response->assertRedirect($listingUrl);
        $this->assertAuthenticatedAs($buyer);
        $this->assertDatabaseHas('users', ['id' => $buyer->id, 'email' => 'listing-buyer@example.test']);
    }

    public function test_registration_ignores_an_external_listing_return_url(): void
    {
        $this->get(route('register', ['return_to' => 'https://example.test/steal']))
            ->assertOk()
            ->assertDontSee('https://example.test/steal');

        $this->post(route('register'), [
            'name' => 'Local Buyer',
            'email' => 'local-buyer@example.test',
            'password' => 'SecurePassword123!',
            'password_confirmation' => 'SecurePassword123!',
        ])->assertRedirect(route('buyer.dashboard'));
    }
}
