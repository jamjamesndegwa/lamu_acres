<?php

namespace App\Console\Commands;

use App\Services\Communications\BulkSmsPreflight;
use App\Services\Communications\MailPreflight;
use App\Support\CommunicationsDelivery;
use Illuminate\Console\Command;

class CommunicationsPreflightCommand extends Command
{
    protected $signature = 'communications:preflight
                            {--sms-recipient= : Authorized internal MSISDN for transactional dry-run only}
                            {--mail-to= : Authorized internal email for SMTP test (not sent from this command)}';

    protected $description = 'Run SMS and mail pre-flight checks without exposing credentials.';

    public function handle(BulkSmsPreflight $sms, MailPreflight $mail): int
    {
        $this->components->info('Lamu Acres communications pre-flight');

        $mailReport = $mail->inspect();
        $this->components->twoColumnDetail('Mail transport', (string) $mailReport['mailer']);
        $this->components->twoColumnDetail('SMTP ready', $mailReport['smtp_ready'] ? 'yes' : 'no');
        $this->components->twoColumnDetail('From address', (string) $mailReport['from_address']);
        $this->line((string) $mailReport['detail']);

        $sync = CommunicationsDelivery::deliverSynchronously();
        $this->components->twoColumnDetail(
            'Transactional delivery',
            $sync ? 'immediate (no queue worker)' : 'queued (requires queue:work)',
        );

        if (! $sync && config('queue.default') === 'database') {
            $this->components->warn('COMMUNICATIONS_SYNC_DELIVERY=false with database queue — email stays queued until a worker runs. Set true or omit the variable on cPanel.');
        }

        if (! $sms->configured()) {
            $this->components->warn('Bulk SMS is not configured. Set BULK_SMS_ENABLED and provider settings in the environment.');

            return self::SUCCESS;
        }

        $recipient = $this->option('sms-recipient');
        $report = $sms->inspect(is_string($recipient) ? $recipient : null);

        $this->components->twoColumnDetail('SMS enabled', 'yes');
        $this->components->twoColumnDetail('Sender ID', (string) ($report['sender_id'] ?? ''));
        $this->components->twoColumnDetail('Dry-run mode', ($report['dry_run_mode'] ?? false) ? 'on' : 'off');

        if (isset($report['profile']) && is_array($report['profile'])) {
            $this->components->twoColumnDetail('Profile', (string) ($report['profile']['client_name'] ?? 'reachable'));
        }

        if (isset($report['balance']) && is_array($report['balance'])) {
            $balance = $report['balance']['balance'] ?? null;
            $currency = $report['balance']['currency'] ?? '';
            $this->components->twoColumnDetail('Balance', $balance !== null ? trim($balance.' '.$currency) : 'available');
        }

        if (array_key_exists('sender_ready', $report)) {
            $this->components->twoColumnDetail('Sender registered', match ($report['sender_ready']) {
                true => 'yes',
                false => 'no',
                default => 'unknown',
            });
        }

        if (isset($report['dry_run_transactional']) && is_array($report['dry_run_transactional'])) {
            $dry = $report['dry_run_transactional'];
            $this->components->twoColumnDetail(
                'Transactional dry-run',
                ($dry['ok'] ?? false) ? 'accepted (id '.($dry['message_id'] ?? 'n/a').')' : 'failed'
            );
        }

        if (isset($report['detail']) && ($report['configured'] ?? false)) {
            $this->line((string) $report['detail']);
        }

        if ($this->option('mail-to')) {
            $this->components->warn('Live mail tests are not dispatched by this command. Use Mail::fake() in tests or send manually to an authorized address.');
        }

        return self::SUCCESS;
    }
}
