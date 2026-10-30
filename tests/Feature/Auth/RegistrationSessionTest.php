<?php

namespace Tests\Feature\Auth;

use Tests\Feature\Foundation\FoundationTestCase;

class RegistrationSessionTest extends FoundationTestCase
{
    public function test_register_form_posts_to_a_relative_url(): void
    {
        $this->get(route('register'))
            ->assertOk()
            ->assertSee('action="/register"', false);
    }

    public function test_buyer_registration_succeeds_with_a_fresh_session(): void
    {
        $email = 'register-session-'.uniqid().'@example.com';

        $this->get(route('register'));

        $this->post(route('register'), [
            'name' => 'Felix Test',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
            'marketing_email_consent' => '0',
            'marketing_sms_consent' => '0',
        ])->assertRedirect(route('buyer.dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => $email]);
    }

    public function test_not_found_page_uses_branded_error_layout(): void
    {
        $this->get('/definitely-missing-page-'.uniqid())
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('Go to home', false)
            ->assertSee(route('home'), false);
    }
}
