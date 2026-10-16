<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class AccountEmailChanged extends Notification
{
    use Queueable;

    public function __construct(public bool $emailChanged = true) {}

    public static function canDeliverEmail(): bool
    {
        $transport = config('mail.mailers.'.config('mail.default').'.transport');

        return filled($transport) && ! in_array($transport, ['log', 'array'], true);
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Confirm your email')
            ->line('Confirm this email address before it is treated as verified.')
            ->action('Confirm email', $this->verificationUrl($notifiable));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Confirm your email',
            'message' => $this->emailChanged ? 'The email on this account changed. Open the confirmation email to verify it.' : 'Open the confirmation email sent to your inbox to verify your address.',
            'url' => route('workspaces.index'),
        ];
    }

    public function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute(
            'account.email.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->email),
            ],
        );
    }
}
