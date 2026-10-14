<?php

namespace Tests\Feature\Admin;

use App\Enums\AssignmentStatus;
use App\Enums\MessageChannel;
use App\Enums\MessageEvent;
use App\Enums\ResearchCommitment;
use App\Enums\Workspace;
use App\Models\ImageryScene;
use App\Models\IntelligenceAudit;
use App\Models\MessageDelivery;
use App\Models\Property;
use App\Models\PropertyAudit;
use App\Models\ResearchAssignment;
use App\Models\User;
use App\Models\VerificationReviewLog;
use App\Services\Access\NavigationBuilder;
use App\Services\Admin\SystemOperations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Feature\Foundation\FoundationTestCase;

class SystemOperationsTest extends FoundationTestCase
{
    public function test_system_operations_requires_the_settings_permission(): void
    {
        $staff = User::factory()->withRoles('staff')->create();
        $buyer = User::factory()->withRoles('buyer')->create();
        $workspaceOnly = User::factory()->withRoles('staff')->withPermissions('workspace.admin')->create();

        $this->get(route('admin.operations.index'))->assertRedirect(route('login'));
        $this->actingAs($staff)->get(route('admin.operations.index'))->assertForbidden();
        $this->actingAs($buyer)->get(route('admin.operations.index'))->assertForbidden();
        $this->actingAs($workspaceOnly)->get(route('admin.operations.index'))->assertForbidden();
    }

    public function test_an_administrator_can_read_operations_without_secrets_or_maintenance_actions(): void
    {
        $admin = User::factory()->withRoles('admin')->create(['name' => 'Amina Operations']);
        $property = Property::factory()->create(['reference' => 'LA-OPS-AUDIT']);

        PropertyAudit::query()->create([
            'property_id' => $property->id,
            'actor_id' => $admin->id,
            'action' => 'published_listing',
            'from_status' => 'draft',
            'to_status' => 'published',
            'changes' => ['token' => 'audit-change-secret-do-not-show'],
        ]);

        IntelligenceAudit::query()->create([
            'subject_type' => ImageryScene::class,
            'subject_id' => 1,
            'actor_id' => $admin->id,
            'action' => 'scene_published',
            'note' => 'intel-note-secret-do-not-show',
            'changes' => ['token' => 'intel-change-secret-do-not-show'],
        ]);

        VerificationReviewLog::query()->create([
            'property_id' => $property->id,
            'actor_id' => $admin->id,
            'decision' => 'accepted_review',
            'reason' => 'verification-reason-secret-do-not-show',
        ]);

        ResearchAssignment::query()->create([
            'commitment' => ResearchCommitment::QuarterlyImagery,
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'due_on' => '2026-04-07',
            'status' => AssignmentStatus::Published,
        ]);

        MessageDelivery::query()->create([
            'idempotency_key' => 'ops-'.Str::lower(Str::random(8)),
            'user_id' => $admin->id,
            'event' => MessageEvent::AccountRegistered,
            'channel' => MessageChannel::Sms,
            'status' => 'failed',
            'attempts' => 1,
            'subject' => 'Desk note',
            'body' => 'delivery-body-secret-do-not-show',
            'destination' => '254700000099',
            'error' => 'delivery-error-secret-do-not-show',
        ]);

        config([
            'queue.default' => 'database',
            'mpesa.driver' => 'daraja',
            'mpesa.base_url' => 'https://payments.example.test',
            'mpesa.consumer_key' => 'mpesa-key-do-not-show',
            'mpesa.consumer_secret' => 'mpesa-secret-do-not-show',
            'mpesa.passkey' => 'mpesa-passkey-do-not-show',
            'mpesa.shortcode' => '99887766',
            'mpesa.callback_url' => 'https://payments.example.test/callback-do-not-show',
            'mpesa.callback_secret' => 'callback-secret-do-not-show',
            'services.sms.endpoint' => 'https://sms.example.test/send-do-not-show',
            'services.sms.token' => 'sms-token-do-not-show',
            'services.sms.from' => 'LamuAcres',
        ]);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\DeliverMessage', 'secret' => 'waiting-payload-secret-do-not-show']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\DeliverMessage', 'secret' => 'failed-payload-secret-do-not-show']),
            'exception' => 'RuntimeException: job-exception-do-not-show',
            'failed_at' => now(),
        ]);

        $this->actingAs($admin)->get(route('admin.operations.index'))
            ->assertOk()
            ->assertSee('System Operations')
            ->assertSee('Application Health')
            ->assertSee('Background Jobs')
            ->assertSee('Scheduled Tasks')
            ->assertSee('Integration Status')
            ->assertSee('Audit and Activity')
            ->assertSee('The sqlite connection responded.')
            ->assertSee('The array store responded.')
            ->assertSee('The database queue responded.')
            ->assertSee('Storage is available.')
            ->assertSee('1 job is waiting.')
            ->assertSee('1 failed job is stored.')
            ->assertSee('DeliverMessage')
            ->assertSee('Failed on the default queue.')
            ->assertSee('Status failed.')
            ->assertSee('Research commitments')
            ->assertSee('research:commitments')
            ->assertSee('M-Pesa reconciliation')
            ->assertSee('mpesa:reconcile')
            ->assertSee('Every minute')
            ->assertSee('Daily')
            ->assertSee('Quarterly imagery comparison')
            ->assertSee('No execution has been recorded.')
            ->assertSee('Daraja settings are present. No payment request was sent.')
            ->assertSee('SMS provider settings are present. No message was sent.')
            ->assertSee('4 map layers are on file.')
            ->assertSee('No imagery scene is on file.')
            ->assertSee('No remote map or imagery service is configured.')
            ->assertSee('Amina Operations')
            ->assertSee('published_listing')
            ->assertSee('LA-OPS-AUDIT')
            ->assertSee('scene_published')
            ->assertSee('ImageryScene #1')
            ->assertSee('accepted_review')
            ->assertSee(route('admin.operations.index').'#audit', false)
            ->assertDontSee('mpesa-key-do-not-show')
            ->assertDontSee('mpesa-secret-do-not-show')
            ->assertDontSee('mpesa-passkey-do-not-show')
            ->assertDontSee('99887766')
            ->assertDontSee('callback-do-not-show')
            ->assertDontSee('callback-secret-do-not-show')
            ->assertDontSee('send-do-not-show')
            ->assertDontSee('sms-token-do-not-show')
            ->assertDontSee('waiting-payload-secret-do-not-show')
            ->assertDontSee('failed-payload-secret-do-not-show')
            ->assertDontSee('job-exception-do-not-show')
            ->assertDontSee('delivery-body-secret-do-not-show')
            ->assertDontSee('254700000099')
            ->assertDontSee('delivery-error-secret-do-not-show')
            ->assertDontSee('audit-change-secret-do-not-show')
            ->assertDontSee('intel-note-secret-do-not-show')
            ->assertDontSee('intel-change-secret-do-not-show')
            ->assertDontSee('verification-reason-secret-do-not-show')
            ->assertDontSee('Reset database')
            ->assertDontSee('Delete jobs')
            ->assertDontSee('Force payment')
            ->assertDontSee('Clear all');

        $panel = collect(app(NavigationBuilder::class)->panels($admin, Workspace::Admin))
            ->firstWhere('title', 'System operations');

        $this->assertSame(route('admin.operations.index'), $panel['href'] ?? null);
    }

    public function test_a_failed_probe_does_not_reveal_the_underlying_error(): void
    {
        $admin = User::factory()->withRoles('admin')->create();

        config([
            'cache.default' => 'database',
            'cache.stores.database.table' => 'missing_cache_table_for_operations',
        ]);

        $report = app(SystemOperations::class)->snapshot();
        $encoded = (string) json_encode($report);
        $cache = collect($report['health'])->firstWhere('name', 'Cache');

        $this->assertSame('unhealthy', $cache['status']);
        $this->assertSame('The cache store did not respond.', $cache['detail']);
        $this->assertStringNotContainsString('SQLSTATE', $encoded);
        $this->assertStringNotContainsString('missing_cache_table_for_operations', $encoded);

        config([
            'cache.default' => 'array',
            'filesystems.default' => 'operations-broken',
            'filesystems.disks.operations-broken' => [
                'driver' => 'operations-broken',
                'key' => 'disk-key-do-not-show',
            ],
        ]);

        $this->actingAs($admin)->get(route('admin.operations.index'))
            ->assertOk()
            ->assertSee('The storage disk did not respond.')
            ->assertDontSee('operations-broken')
            ->assertDontSee('disk-key-do-not-show')
            ->assertDontSee('SQLSTATE');

        $operations = $this->partialMock(SystemOperations::class, function ($mock): void {
            $mock->shouldReceive('snapshot')->andThrow(new RuntimeException('controller-secret-do-not-show'));
        });
        $this->instance(SystemOperations::class, $operations);

        $this->actingAs($admin)->get(route('admin.operations.index'))
            ->assertOk()
            ->assertSee('The operations report could not be read.')
            ->assertDontSee('controller-secret-do-not-show');
    }
}
