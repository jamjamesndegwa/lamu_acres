<?php

namespace App\Jobs;

use App\Contracts\Communications\SmsSender;
use App\Enums\MessageCampaignStatus;
use App\Enums\MessageChannel;
use App\Exceptions\Communications\SmsProviderException;
use App\Exceptions\Communications\SmsUncertainDeliveryException;
use App\Mail\DeskMessageMail;
use App\Models\MessageCampaign;
use App\Models\User;
use App\Services\Communications\CampaignDesk;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class DispatchMessageCampaign implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(public int $campaignId) {}

    public function handle(CampaignDesk $campaigns, SmsSender $sms): void
    {
        $campaign = MessageCampaign::query()->find($this->campaignId);

        if ($campaign === null || $campaign->status === MessageCampaignStatus::Completed) {
            return;
        }

        $campaign->forceFill(['status' => MessageCampaignStatus::Sending])->save();

        $sent = 0;
        $failed = 0;

        try {
            if ($campaign->channel === MessageChannel::Sms) {
                [$sent, $failed] = $this->dispatchSms($campaign, $campaigns, $sms);
            } else {
                [$sent, $failed] = $this->dispatchEmail($campaign, $campaigns);
            }

            $campaign->forceFill([
                'status' => MessageCampaignStatus::Completed,
                'sent_count' => $sent,
                'failed_count' => $failed,
                'completed_at' => now(),
            ])->save();
        } catch (Throwable $exception) {
            $campaign->forceFill([
                'status' => MessageCampaignStatus::Failed,
                'failed_count' => $failed,
            ])->save();

            throw $exception;
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dispatchSms(MessageCampaign $campaign, CampaignDesk $campaigns, SmsSender $sms): array
    {
        $sent = 0;
        $failed = 0;

        $campaigns->recipientQuery($campaign)->chunkById(50, function ($users) use ($campaign, $campaigns, $sms, &$sent, &$failed): void {
            $batch = [];
            $deliveries = [];

            foreach ($users as $user) {
                if (! $user instanceof User) {
                    continue;
                }

                $body = $campaigns->appendComplianceFooter($user, $campaign->body);
                $phone = $this->normalizePhone((string) $user->phone);
                $delivery = $campaigns->recordDelivery($campaign, $user, $campaign->subject, $body, $phone);

                if ($delivery === null) {
                    continue;
                }

                $batch[] = ['phone' => $phone, 'body' => $body];
                $deliveries[] = $delivery;
            }

            if ($batch === []) {
                return;
            }

            if (! $sms->configured()) {
                foreach ($deliveries as $delivery) {
                    $delivery->forceFill(['status' => 'skipped', 'error' => 'SMS provider is not configured.'])->save();
                    $failed++;
                }

                return;
            }

            try {
                $outcomes = $sms->sendBulk($batch);

                foreach ($deliveries as $index => $delivery) {
                    $phone = $batch[$index]['phone'];
                    $outcome = $outcomes[$phone] ?? null;

                    if ($outcome?->uncertain) {
                        $delivery->forceFill([
                            'status' => 'uncertain',
                            'attempts' => 1,
                            'error' => $outcome->error ?? 'SmsUncertainDeliveryException: outcome unknown.',
                        ])->save();
                        $failed++;

                        continue;
                    }

                    if ($outcome?->success) {
                        $delivery->forceFill([
                            'status' => 'sent',
                            'provider_reference' => $outcome->messageId,
                            'provider_meta' => $outcome->meta(),
                            'attempts' => 1,
                            'sent_at' => now(),
                            'error' => null,
                        ])->save();
                        $sent++;

                        continue;
                    }

                    $delivery->forceFill([
                        'status' => 'failed',
                        'attempts' => 1,
                        'error' => $outcome?->error ?? 'SMS delivery failed.',
                    ])->save();
                    $failed++;
                }
            } catch (SmsUncertainDeliveryException) {
                foreach ($deliveries as $delivery) {
                    $delivery->forceFill([
                        'status' => 'uncertain',
                        'attempts' => 1,
                        'error' => 'SmsUncertainDeliveryException: outcome unknown.',
                    ])->save();
                    $failed++;
                }
            } catch (SmsProviderException $exception) {
                foreach ($deliveries as $delivery) {
                    $delivery->forceFill([
                        'status' => 'failed',
                        'attempts' => 1,
                        'error' => class_basename($exception).': delivery failed.',
                    ])->save();
                    $failed++;
                }

                if ($exception->retriable) {
                    throw $exception;
                }
            }
        });

        return [$sent, $failed];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dispatchEmail(MessageCampaign $campaign, CampaignDesk $campaigns): array
    {
        $sent = 0;
        $failed = 0;

        $campaigns->recipientQuery($campaign)->chunkById(25, function ($users) use ($campaign, $campaigns, &$sent, &$failed): void {
            foreach ($users as $user) {
                if (! $user instanceof User) {
                    continue;
                }

                $body = $campaigns->appendComplianceFooter($user, $campaign->body);
                $delivery = $campaigns->recordDelivery($campaign, $user, $campaign->subject, $body, $user->email);

                if ($delivery === null) {
                    continue;
                }

                try {
                    Mail::to($user->email)->send(new DeskMessageMail($campaign->subject, $body));
                    $delivery->forceFill([
                        'status' => 'sent',
                        'attempts' => 1,
                        'sent_at' => now(),
                        'error' => null,
                    ])->save();
                    $sent++;
                } catch (Throwable $exception) {
                    $delivery->forceFill([
                        'status' => 'failed',
                        'attempts' => 1,
                        'error' => class_basename($exception).': delivery failed.',
                    ])->save();
                    $failed++;
                }
            }
        });

        return [$sent, $failed];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '0')) {
            $digits = '254'.substr($digits, 1);
        }

        return $digits;
    }
}
