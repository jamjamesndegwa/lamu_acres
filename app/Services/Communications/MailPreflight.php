<?php

namespace App\Services\Communications;

class MailPreflight
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(): array
    {
        $mailer = (string) config('mail.default');
        $transport = config('mail.mailers.'.$mailer);

        $host = is_array($transport) ? ($transport['host'] ?? null) : null;
        $port = is_array($transport) ? ($transport['port'] ?? null) : null;
        $encryption = is_array($transport) ? ($transport['encryption'] ?? $transport['scheme'] ?? null) : null;
        $username = is_array($transport) ? ($transport['username'] ?? null) : null;

        $smtpReady = $mailer === 'smtp'
            && filled($host)
            && filled($port)
            && filled($username)
            && filled($transport['password'] ?? null)
            && filled(config('mail.from.address'));

        return [
            'mailer' => $mailer,
            'smtp_ready' => $smtpReady,
            'host_set' => filled($host),
            'port' => $port,
            'encryption' => $encryption,
            'authenticated_mailbox' => filled($username),
            'from_address' => (string) config('mail.from.address'),
            'queue_connection' => (string) config('queue.default'),
            'detail' => $smtpReady
                ? 'SMTP mailbox settings are present for cPanel delivery.'
                : ($mailer === 'log'
                    ? 'Mail is logging locally; configure SMTP before production email.'
                    : 'SMTP settings are incomplete.'),
        ];
    }
}
