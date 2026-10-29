<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Throwable;

#[Signature('mail:verify-smtp {to : Inbox that may receive the test (use an @lamuacres.co.ke staff address)}')]
#[Description('Send one synchronous SMTP test message using current MAIL_* settings')]
class MailVerifySmtpCommand extends Command
{
    public function handle(): int
    {
        $to = (string) $this->argument('to');

        if (! filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->error('Provide a valid email address.');

            return self::FAILURE;
        }

        if ((string) config('mail.default') !== 'smtp') {
            $this->error('MAIL_MAILER must be smtp (currently: '.config('mail.default').').');

            return self::FAILURE;
        }

        $host = config('mail.mailers.smtp.host');
        $username = config('mail.mailers.smtp.username');
        $password = config('mail.mailers.smtp.password');

        if (! filled($host) || ! filled($username) || ! filled($password)) {
            $this->error('MAIL_HOST, MAIL_USERNAME, and MAIL_PASSWORD must be set (run communications:preflight).');

            return self::FAILURE;
        }

        try {
            Mail::raw(
                'This is a Lamu Acres SMTP test sent at '.now()->toIso8601String().' from '.config('app.url').'.',
                function ($message) use ($to): void {
                    $message->to($to)->subject('Lamu Acres SMTP test');
                },
            );
        } catch (Throwable $exception) {
            $this->error('SMTP send failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Test message sent to '.$to.'. Check the inbox (and spam folder).');

        return self::SUCCESS;
    }
}
