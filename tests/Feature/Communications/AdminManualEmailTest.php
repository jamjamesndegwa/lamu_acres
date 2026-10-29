<?php

namespace Tests\Feature\Communications;

use App\Enums\MessageDeliveryStatus;
use App\Enums\MessageEvent;
use App\Enums\Workspace;
use App\Mail\DeskMessageMail;
use App\Models\MessageDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Feature\Foundation\FoundationTestCase;

class AdminManualEmailTest extends FoundationTestCase
{
    public function test_admin_can_send_manual_email_to_user_inbox(): void
    {
        config()->set('communications.deliver_synchronously', true);
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', 'mail.example.test');
        config()->set('mail.mailers.smtp.port', 587);
        config()->set('mail.mailers.smtp.username', 'info@lamuacres.co.ke');
        config()->set('mail.mailers.smtp.password', 'secret');
        config()->set('mail.from.address', 'info@lamuacres.co.ke');

        Mail::fake();

        $admin = User::factory()->withRoles('super_admin')->withPermissions('communications.email.view')->create();
        $recipient = User::factory()->create(['email' => 'buyer@lamuacres.co.ke']);

        $this->actingAs($admin)
            ->withSession(['workspace' => Workspace::Admin->value])
            ->post(route('admin.communications.manual-email.store'), [
                'user_id' => $recipient->id,
                'subject' => 'Test subject',
                'body' => 'Hello from the desk.',
            ])
            ->assertRedirect();

        Mail::assertSent(DeskMessageMail::class, function (DeskMessageMail $mail): bool {
            return $mail->hasTo('buyer@lamuacres.co.ke');
        });

        $this->assertDatabaseHas('message_deliveries', [
            'user_id' => $recipient->id,
            'event' => MessageEvent::StaffManualEmail->value,
            'status' => MessageDeliveryStatus::Sent->value,
            'destination' => 'buyer@lamuacres.co.ke',
        ]);
    }

    public function test_admin_can_override_destination_email(): void
    {
        config()->set('communications.deliver_synchronously', true);
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', 'mail.example.test');
        config()->set('mail.mailers.smtp.port', 587);
        config()->set('mail.mailers.smtp.username', 'info@lamuacres.co.ke');
        config()->set('mail.mailers.smtp.password', 'secret');
        config()->set('mail.from.address', 'info@lamuacres.co.ke');

        Mail::fake();

        $admin = User::factory()->withRoles('super_admin')->withPermissions('communications.email.view')->create();
        $recipient = User::factory()->create(['email' => 'buyer@lamuacres.co.ke']);

        $this->actingAs($admin)
            ->withSession(['workspace' => Workspace::Admin->value])
            ->post(route('admin.communications.manual-email.store'), [
                'user_id' => $recipient->id,
                'destination_email' => 'alternate@lamuacres.co.ke',
                'subject' => 'Override',
                'body' => 'Sent elsewhere.',
            ])
            ->assertRedirect();

        Mail::assertSent(DeskMessageMail::class, fn (DeskMessageMail $mail): bool => $mail->hasTo('alternate@lamuacres.co.ke'));

        $delivery = MessageDelivery::query()->where('user_id', $recipient->id)->latest('id')->first();
        $this->assertSame('alternate@lamuacres.co.ke', $delivery?->destination);
    }
}
