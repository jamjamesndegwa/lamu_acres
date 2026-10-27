<?php

namespace App\Services\Admin;

use App\Enums\OperationsStatus;
use App\Enums\PaymentStatus;
use App\Models\BrokerIntroductionAudit;
use App\Models\EvidenceAccessLog;
use App\Models\ImageryScene;
use App\Models\IntelligenceAudit;
use App\Models\MapLayer;
use App\Models\MessageDelivery;
use App\Models\PropertyAudit;
use App\Models\ResearchAssignment;
use App\Models\SalesAudit;
use App\Models\SubscriptionPayment;
use App\Models\VerificationReviewLog;
use Carbon\CarbonInterface;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SystemOperations
{
    /**
     * @return array{
     *     health: array<int, array{name: string, status: string, label: string, detail: string}>,
     *     jobs: array{
     *         checks: array<int, array{name: string, status: string, label: string, detail: string}>,
     *         results: array<int, array{label: string, detail: string, at: string}>
     *     },
     *     schedules: array<int, array{name: string, command: string, frequency: string, next: string, result: string, status: string, label: string}>,
     *     integrations: array<int, array{name: string, status: string, label: string, detail: string}>,
     *     activity: array<int, array{actor: string, action: string, at: string, record: string}>,
     *     activity_unreadable: bool
     * }
     */
    public function snapshot(): array
    {
        $queue = $this->queue();
        $activity = $this->activity();

        return $this->scrub([
            'health' => [
                $this->database(),
                $this->cache(),
                $queue['configuration'],
                $this->storage(),
            ],
            'jobs' => [
                'checks' => [
                    $queue['configuration'],
                    $queue['pending'],
                    $queue['failed'],
                ],
                'results' => $this->jobResults(),
            ],
            'schedules' => $this->schedules(),
            'integrations' => [
                $this->mpesa(),
                $this->sms(),
                $this->mapping(),
            ],
            'activity' => $activity['rows'],
            'activity_unreadable' => $activity['unreadable'],
        ]);
    }

    /**
     * @return array{
     *     health: array<int, array{name: string, status: string, label: string, detail: string}>,
     *     jobs: array{checks: array<int, array{name: string, status: string, label: string, detail: string}>, results: array<int, array{label: string, detail: string, at: string}>},
     *     schedules: array<int, array{name: string, command: string, frequency: string, next: string, result: string, status: string, label: string}>,
     *     integrations: array<int, array{name: string, status: string, label: string, detail: string}>,
     *     activity: array<int, array{actor: string, action: string, at: string, record: string}>,
     *     activity_unreadable: bool
     * }
     */
    public function unavailable(): array
    {
        $failure = $this->check('System operations', OperationsStatus::Unhealthy, 'The operations report could not be read.');

        return [
            'health' => [$failure],
            'jobs' => [
                'checks' => [$failure],
                'results' => [],
            ],
            'schedules' => [],
            'integrations' => [$failure],
            'activity' => [],
            'activity_unreadable' => true,
        ];
    }

    /**
     * @return array{name: string, status: string, label: string, detail: string}
     */
    private function database(): array
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver");

        if ($connection === '' || $driver === '') {
            return $this->check('Database', OperationsStatus::NotConfigured, 'No database connection is selected.');
        }

        try {
            DB::connection()->select('select 1 as ok');

            return $this->check('Database', OperationsStatus::Healthy, 'The '.$driver.' connection responded.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->check('Database', OperationsStatus::Unhealthy, 'The database connection did not respond.');
        }
    }

    /**
     * @return array{name: string, status: string, label: string, detail: string}
     */
    private function cache(): array
    {
        $store = (string) config('cache.default');

        if ($store === '' || $store === 'null') {
            return $this->check('Cache', OperationsStatus::NotConfigured, 'No cache store is selected.');
        }

        try {
            Cache::store($store)->get('system-operations:probe');

            return $this->check('Cache', OperationsStatus::Healthy, 'The '.$store.' store responded.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->check('Cache', OperationsStatus::Unhealthy, 'The cache store did not respond.');
        }
    }

    /**
     * @return array{name: string, status: string, label: string, detail: string}
     */
    private function storage(): array
    {
        $disk = (string) config('filesystems.default');
        $driver = config("filesystems.disks.{$disk}.driver");

        if ($disk === '' || ! is_string($driver) || $driver === '') {
            return $this->check('Storage', OperationsStatus::NotConfigured, 'Storage is not configured.');
        }

        if ($driver === 's3' && (blank(config("filesystems.disks.{$disk}.bucket")) || blank(config("filesystems.disks.{$disk}.key")))) {
            return $this->check('Storage', OperationsStatus::NotConfigured, 'Object storage is not configured.');
        }

        try {
            Storage::disk($disk)->exists('system-operations-availability');

            return $this->check('Storage', OperationsStatus::Healthy, 'Storage is available.');
        } catch (Throwable $exception) {
            report($exception);

            return $this->check('Storage', OperationsStatus::Unhealthy, 'The storage disk did not respond.');
        }
    }

    /**
     * @return array{
     *     configuration: array{name: string, status: string, label: string, detail: string},
     *     pending: array{name: string, status: string, label: string, detail: string},
     *     failed: array{name: string, status: string, label: string, detail: string}
     * }
     */
    private function queue(): array
    {
        $connection = (string) config('queue.default');
        $driver = (string) config("queue.connections.{$connection}.driver");

        if ($connection === '' || $driver === '' || $driver === 'null') {
            return [
                'configuration' => $this->check('Queue', OperationsStatus::NotConfigured, 'No queue connection is selected.'),
                'pending' => $this->check('Pending jobs', OperationsStatus::NotConfigured, 'Pending jobs are not available until a queue connection is selected.'),
                'failed' => $this->failedJobs(),
            ];
        }

        if ($driver === 'database') {
            try {
                $pendingCount = $this->jobsQuery()->count();
                $configuration = $this->check('Queue', OperationsStatus::Healthy, 'The database queue responded.');
                $pending = $this->check('Pending jobs', OperationsStatus::Healthy, $this->countPhrase(
                    $pendingCount,
                    '1 job is waiting.',
                    'jobs are waiting.',
                    'No jobs are waiting.',
                ));
            } catch (Throwable $exception) {
                report($exception);
                $configuration = $this->check('Queue', OperationsStatus::Unhealthy, 'The database queue did not respond.');
                $pending = $this->check('Pending jobs', OperationsStatus::Unhealthy, 'Pending jobs could not be read.');
            }
        } elseif ($driver === 'sync') {
            $configuration = $this->check('Queue', OperationsStatus::Healthy, 'The sync driver runs jobs during the request.');
            $pending = $this->check('Pending jobs', OperationsStatus::Configured, 'Pending jobs are not stored for the sync driver.');
        } else {
            $configuration = $this->check('Queue', OperationsStatus::Configured, 'The '.$driver.' driver is selected. A job was not dispatched.');
            $pending = $this->check('Pending jobs', OperationsStatus::Configured, 'Pending jobs are not stored for the '.$driver.' driver.');
        }

        return [
            'configuration' => $configuration,
            'pending' => $pending,
            'failed' => $this->failedJobs(),
        ];
    }

    /**
     * @return array{name: string, status: string, label: string, detail: string}
     */
    private function failedJobs(): array
    {
        $driver = (string) config('queue.failed.driver');

        if ($driver === '' || $driver === 'null') {
            return $this->check('Failed jobs', OperationsStatus::NotConfigured, 'Failed jobs are not stored.');
        }

        if (! in_array($driver, ['database-uuids', 'database'], true)) {
            return $this->check('Failed jobs', OperationsStatus::Configured, 'Failed jobs are stored outside the database and are not listed here.');
        }

        try {
            $count = $this->failedJobsQuery()->count();

            return $this->check('Failed jobs', OperationsStatus::Healthy, $this->countPhrase(
                $count,
                '1 failed job is stored.',
                'failed jobs are stored.',
                'No failed jobs are stored.',
            ));
        } catch (Throwable $exception) {
            report($exception);

            return $this->check('Failed jobs', OperationsStatus::Unhealthy, 'Failed jobs could not be read.');
        }
    }

    /**
     * @return array<int, array{label: string, detail: string, at: string}>
     */
    private function jobResults(): array
    {
        $results = [];

        if (in_array((string) config('queue.failed.driver'), ['database-uuids', 'database'], true)) {
            try {
                $rows = $this->failedJobsQuery()->orderByDesc('id')->limit(5)->get(['id', 'queue', 'payload', 'failed_at']);

                foreach ($rows as $row) {
                    $results[] = [
                        'label' => $this->jobName($row->payload),
                        'detail' => 'Failed on the '.$this->plainQueueName($row->queue).' queue.',
                        'at' => $this->timestamp($row->failed_at),
                    ];
                }
            } catch (Throwable $exception) {
                report($exception);
                $results[] = [
                    'label' => 'Failed jobs',
                    'detail' => 'The last failure could not be read.',
                    'at' => '',
                ];
            }
        }

        try {
            $delivery = MessageDelivery::query()->latest('id')->first(['id', 'status', 'sent_at', 'updated_at']);

            if ($delivery !== null) {
                $results[] = [
                    'label' => 'Message delivery',
                    'detail' => 'Status '.$delivery->status.'.',
                    'at' => $this->timestamp($delivery->sent_at ?? $delivery->updated_at),
                ];
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        return $results;
    }

    /**
     * @return array<int, array{name: string, command: string, frequency: string, next: string, result: string, status: string, label: string}>
     */
    private function schedules(): array
    {
        $known = [
            'research:commitments' => 'Research commitments',
            'mpesa:reconcile' => 'M-Pesa reconciliation',
        ];
        $found = [];

        try {
            foreach (app(Schedule::class)->events() as $event) {
                if (! $event instanceof Event) {
                    continue;
                }

                $command = $this->commandName($event);
                $found[$command] = $this->scheduleRow($event, $command, $known[$command] ?? null);
            }
        } catch (Throwable $exception) {
            report($exception);

            $rows = [];

            foreach ($known as $command => $name) {
                $rows[] = $this->schedulePlaceholder($name, $command, OperationsStatus::Unhealthy, 'The schedule could not be read.');
            }

            return $rows;
        }

        $rows = [];

        foreach ($known as $command => $name) {
            $rows[] = $found[$command] ?? $this->schedulePlaceholder($name, $command, OperationsStatus::NotConfigured, 'This task is not on the schedule.');
            unset($found[$command]);
        }

        foreach ($found as $row) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array{name: string, command: string, frequency: string, next: string, result: string, status: string, label: string}
     */
    private function scheduleRow(Event $event, string $command, ?string $knownName): array
    {
        $summary = $event->getSummaryForDisplay();
        $name = $knownName ?? (str_contains($summary, 'artisan') ? $command : $summary);
        $expression = $event->getExpression();
        $frequency = $this->frequency($expression);
        $status = OperationsStatus::Configured;

        return [
            'name' => $name,
            'command' => $command,
            'frequency' => $frequency === $expression ? $expression : $frequency.' · '.$expression,
            'next' => $this->nextRun($event),
            'result' => $this->scheduleResult($command),
            'status' => $status->value,
            'label' => $status->label(),
        ];
    }

    /**
     * @return array{name: string, command: string, frequency: string, next: string, result: string, status: string, label: string}
     */
    private function schedulePlaceholder(string $name, string $command, OperationsStatus $status, string $result): array
    {
        return [
            'name' => $name,
            'command' => $command,
            'frequency' => 'Not scheduled',
            'next' => 'Next run is not available.',
            'result' => $result,
            'status' => $status->value,
            'label' => $status->label(),
        ];
    }

    private function scheduleResult(string $command): string
    {
        try {
            return match ($command) {
                'research:commitments' => $this->researchResult(),
                'mpesa:reconcile' => $this->mpesaResult(),
                default => 'No execution has been recorded.',
            };
        } catch (Throwable $exception) {
            report($exception);

            return 'The last result could not be read.';
        }
    }

    private function researchResult(): string
    {
        $assignment = ResearchAssignment::query()->latest('updated_at')->first();

        if ($assignment === null) {
            return 'No execution has been recorded.';
        }

        return 'Updated '.$this->timestamp($assignment->updated_at)
            .' · '.$assignment->commitment->label()
            .' · '.$assignment->status->label();
    }

    private function mpesaResult(): string
    {
        $payment = SubscriptionPayment::query()->whereNotNull('reconciled_at')->latest('reconciled_at')->first();

        if ($payment === null) {
            return 'No execution has been recorded.';
        }

        $status = $payment->status instanceof PaymentStatus ? $payment->status->label() : 'Recorded';

        return 'Last reconciliation '.$this->timestamp($payment->reconciled_at).' · '.$status;
    }

    /**
     * @return array{name: string, status: string, label: string, detail: string}
     */
    private function mpesa(): array
    {
        $driver = (string) config('mpesa.driver');
        $required = ['base_url', 'consumer_key', 'consumer_secret', 'passkey', 'shortcode', 'callback_url', 'callback_secret'];
        $complete = collect($required)->every(fn (string $key): bool => filled(config('mpesa.'.$key)));

        if ($driver === '' || $driver === 'unconfigured') {
            return $this->check('M-Pesa', OperationsStatus::NotConfigured, 'M-Pesa is not configured. No payment request was sent.');
        }

        if ($driver !== 'daraja') {
            return $this->check('M-Pesa', OperationsStatus::Unhealthy, 'The selected M-Pesa driver is not available. No payment request was sent.');
        }

        if (! $complete) {
            return $this->check('M-Pesa', OperationsStatus::Unhealthy, 'Daraja is selected and a required setting is missing. No payment request was sent.');
        }

        return $this->check('M-Pesa', OperationsStatus::Configured, 'Daraja settings are present. No payment request was sent.');
    }

    /**
     * @return array{name: string, status: string, label: string, detail: string}
     */
    private function sms(): array
    {
        $enabled = (bool) config('services.bulk_sms.enabled');
        $baseUrl = filled(config('services.bulk_sms.base_url'));
        $clientId = filled(config('services.bulk_sms.client_id'));
        $apiKey = filled(config('services.bulk_sms.api_key'));

        if (! $enabled && ! $baseUrl && ! $clientId && ! $apiKey) {
            return $this->check('SMS', OperationsStatus::NotConfigured, 'SMS provider settings are not present. No message was sent.');
        }

        if (! $enabled || ! $baseUrl || ! $clientId || ! $apiKey) {
            return $this->check('SMS', OperationsStatus::Unhealthy, 'SMS provider settings are incomplete. No message was sent.');
        }

        return $this->check('SMS', OperationsStatus::Configured, 'SMS provider settings are present. No message was sent.');
    }

    /**
     * @return array{name: string, status: string, label: string, detail: string}
     */
    private function mapping(): array
    {
        $layers = $this->storedCount(MapLayer::class);
        $scenes = $this->storedCount(ImageryScene::class);
        $detail = 'No remote map or imagery service is configured. Layers and scenes stay in the application and are not fetched from a provider.';

        if ($layers === null || $scenes === null) {
            return $this->check('Mapping and imagery', OperationsStatus::Unhealthy, $detail.' A stored count could not be read.');
        }

        $detail .= ' '.$this->countPhrase($layers, '1 map layer is on file.', 'map layers are on file.', 'No map layer is on file.');
        $detail .= ' '.$this->countPhrase($scenes, '1 imagery scene is on file.', 'imagery scenes are on file.', 'No imagery scene is on file.');

        return $this->check(
            'Mapping and imagery',
            $layers > 0 || $scenes > 0 ? OperationsStatus::Configured : OperationsStatus::NotConfigured,
            $detail,
        );
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function storedCount(string $model): ?int
    {
        try {
            return $model::query()->count();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * @return array{rows: array<int, array{actor: string, action: string, at: string, record: string}>, unreadable: bool}
     */
    private function activity(): array
    {
        $rows = [];
        $unreadable = false;

        foreach ([
            fn (): array => $this->propertyActivity(),
            fn (): array => $this->intelligenceActivity(),
            fn (): array => $this->salesActivity(),
            fn (): array => $this->introductionActivity(),
            fn (): array => $this->verificationActivity(),
            fn (): array => $this->evidenceActivity(),
        ] as $reader) {
            try {
                array_push($rows, ...$reader());
            } catch (Throwable $exception) {
                report($exception);
                $unreadable = true;
            }
        }

        usort($rows, fn (array $left, array $right): int => strcmp($right['sort'], $left['sort']));

        $rows = array_map(function (array $row): array {
            unset($row['sort']);

            return $row;
        }, array_slice($rows, 0, 20));

        return ['rows' => $rows, 'unreadable' => $unreadable];
    }

    /**
     * @return array<int, array{actor: string, action: string, at: string, record: string, sort: string}>
     */
    private function propertyActivity(): array
    {
        return PropertyAudit::query()
            ->select(['id', 'property_id', 'actor_id', 'action', 'from_status', 'to_status', 'created_at'])
            ->with(['actor:id,name', 'property:id,reference'])
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(function (PropertyAudit $audit): array {
                $action = (string) $audit->action;

                if ($audit->from_status || $audit->to_status) {
                    $action .= ' · '.($audit->from_status ?: '—').' → '.($audit->to_status ?: '—');
                }

                return $this->activityRow(
                    $audit->actor?->name,
                    $action,
                    $audit->created_at,
                    $audit->property?->reference ?: 'Property #'.$audit->property_id,
                );
            })
            ->all();
    }

    /**
     * @return array<int, array{actor: string, action: string, at: string, record: string, sort: string}>
     */
    private function intelligenceActivity(): array
    {
        return IntelligenceAudit::query()
            ->select(['id', 'subject_type', 'subject_id', 'actor_id', 'action', 'created_at'])
            ->with('actor:id,name')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (IntelligenceAudit $audit): array => $this->activityRow(
                $audit->actor?->name,
                (string) $audit->action,
                $audit->created_at,
                trim(class_basename((string) $audit->subject_type).' #'.$audit->subject_id),
            ))
            ->all();
    }

    /**
     * @return array<int, array{actor: string, action: string, at: string, record: string, sort: string}>
     */
    private function salesActivity(): array
    {
        return SalesAudit::query()
            ->select(['id', 'sales_transaction_id', 'actor_id', 'event', 'created_at'])
            ->with(['actor:id,name', 'transaction:id,reference'])
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (SalesAudit $audit): array => $this->activityRow(
                $audit->actor?->name,
                (string) $audit->event,
                $audit->created_at,
                $audit->transaction?->reference ?: 'Sale #'.$audit->sales_transaction_id,
            ))
            ->all();
    }

    /**
     * @return array<int, array{actor: string, action: string, at: string, record: string, sort: string}>
     */
    private function introductionActivity(): array
    {
        return BrokerIntroductionAudit::query()
            ->select(['id', 'broker_introduction_id', 'actor_id', 'action', 'created_at'])
            ->with('actor:id,name')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (BrokerIntroductionAudit $audit): array => $this->activityRow(
                $audit->actor?->name,
                (string) $audit->action,
                $audit->created_at,
                'Introduction #'.$audit->broker_introduction_id,
            ))
            ->all();
    }

    /**
     * @return array<int, array{actor: string, action: string, at: string, record: string, sort: string}>
     */
    private function verificationActivity(): array
    {
        return VerificationReviewLog::query()
            ->select(['id', 'property_id', 'actor_id', 'decision', 'created_at'])
            ->with(['actor:id,name', 'property:id,reference'])
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (VerificationReviewLog $log): array => $this->activityRow(
                $log->actor?->name,
                (string) $log->decision,
                $log->created_at,
                $log->property?->reference ?: 'Property #'.$log->property_id,
            ))
            ->all();
    }

    /**
     * @return array<int, array{actor: string, action: string, at: string, record: string, sort: string}>
     */
    private function evidenceActivity(): array
    {
        return EvidenceAccessLog::query()
            ->select(['id', 'evidence_file_id', 'user_id', 'outcome', 'accessed_at'])
            ->with('user:id,name')
            ->latest('id')
            ->limit(15)
            ->get()
            ->map(fn (EvidenceAccessLog $log): array => $this->activityRow(
                $log->user?->name,
                'Evidence '.($log->outcome !== '' ? $log->outcome : 'access'),
                $log->accessed_at,
                'Evidence file #'.$log->evidence_file_id,
            ))
            ->all();
    }

    /**
     * @return array{actor: string, action: string, at: string, record: string, sort: string}
     */
    private function activityRow(?string $actor, string $action, mixed $at, string $record): array
    {
        $moment = $at instanceof CarbonInterface ? $at : (is_string($at) && $at !== '' ? Carbon::parse($at) : null);

        return [
            'actor' => filled($actor) ? $actor : 'System',
            'action' => $action,
            'at' => $this->timestamp($moment),
            'record' => $record,
            'sort' => $moment?->toIso8601String() ?? '',
        ];
    }

    private function jobsQuery(): Builder
    {
        $connection = config('queue.connections.database.connection');
        $table = (string) config('queue.connections.database.table', 'jobs');

        $query = filled($connection) ? DB::connection((string) $connection)->table($table) : DB::table($table);

        return $query;
    }

    private function failedJobsQuery(): Builder
    {
        $connection = config('queue.failed.database');
        $table = (string) config('queue.failed.table', 'failed_jobs');

        return filled($connection) ? DB::connection((string) $connection)->table($table) : DB::table($table);
    }

    private function jobName(mixed $payload): string
    {
        if (! is_string($payload)) {
            return 'Queued job';
        }

        $decoded = json_decode($payload, true);
        $name = is_array($decoded) ? ($decoded['displayName'] ?? null) : null;

        if (! is_string($name) || $name === '' || str_contains($name, "\n") || strlen($name) > 180) {
            return 'Queued job';
        }

        $base = class_basename(str_replace('\\\\', '\\', $name));

        return $base !== '' ? $base : 'Queued job';
    }

    private function plainQueueName(mixed $queue): string
    {
        if (! is_string($queue) || $queue === '' || strlen($queue) > 80 || str_contains($queue, ' ')) {
            return 'application';
        }

        return $queue;
    }

    private function commandName(Event $event): string
    {
        $command = trim((string) $event->command);

        if (preg_match('/\s(\S+)$/', $command, $matches) === 1) {
            return $matches[1];
        }

        return $command !== '' ? $command : 'Scheduled task';
    }

    private function frequency(string $expression): string
    {
        return match ($expression) {
            '* * * * *' => 'Every minute',
            '0 * * * *' => 'Hourly',
            '0 0 * * *' => 'Daily',
            default => $expression,
        };
    }

    private function nextRun(Event $event): string
    {
        try {
            return $event->nextRunDate()->toDayDateTimeString();
        } catch (Throwable $exception) {
            report($exception);

            return 'Next run is not available.';
        }
    }

    private function timestamp(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->toDayDateTimeString();
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->toDayDateTimeString();
        }

        return '';
    }

    private function countPhrase(int $count, string $one, string $many, string $zero): string
    {
        return match (true) {
            $count === 0 => $zero,
            $count === 1 => $one,
            default => $count.' '.$many,
        };
    }

    /**
     * @return array{name: string, status: string, label: string, detail: string}
     */
    private function check(string $name, OperationsStatus $status, string $detail): array
    {
        return [
            'name' => $name,
            'status' => $status->value,
            'label' => $status->label(),
            'detail' => $detail,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function scrub(array $report): array
    {
        $secrets = $this->secrets();

        array_walk_recursive($report, function (mixed &$value) use ($secrets): void {
            if (! is_string($value)) {
                return;
            }

            foreach ($secrets as $secret) {
                $value = str_replace($secret, '[withheld]', $value);
            }
        });

        return $report;
    }

    /**
     * @return array<int, string>
     */
    private function secrets(): array
    {
        $values = [
            config('app.key'),
            config('mpesa.base_url'),
            config('mpesa.consumer_key'),
            config('mpesa.consumer_secret'),
            config('mpesa.passkey'),
            config('mpesa.shortcode'),
            config('mpesa.callback_url'),
            config('mpesa.callback_secret'),
            config('services.bulk_sms.api_key'),
            config('services.ses.key'),
            config('services.ses.secret'),
            config('mail.mailers.smtp.password'),
            config('queue.connections.sqs.key'),
            config('queue.connections.sqs.secret'),
            config('filesystems.disks.s3.key'),
            config('filesystems.disks.s3.secret'),
            config('database.connections.mysql.password'),
            config('database.connections.pgsql.password'),
        ];

        return array_values(array_unique(array_filter(
            $values,
            fn (mixed $value): bool => is_string($value) && strlen($value) >= 8,
        )));
    }
}
