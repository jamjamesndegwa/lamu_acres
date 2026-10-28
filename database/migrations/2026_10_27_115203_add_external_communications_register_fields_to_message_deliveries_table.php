<?php

use App\Enums\MessageEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_deliveries', function (Blueprint $table) {
            $table->string('queue_name')->nullable()->after('status');
            $table->timestamp('queued_at')->nullable()->after('queue_name');
            $table->timestamp('last_attempt_at')->nullable()->after('queued_at');
        });

        Schema::table('message_deliveries', function (Blueprint $table) {
            $table->index(['channel', 'status', 'created_at'], 'message_deliveries_channel_status_created');
            $table->index(['user_id', 'created_at'], 'message_deliveries_user_created');
        });

        $now = now();

        foreach ([MessageEvent::PasswordResetRequested, MessageEvent::EmailVerificationRequested] as $event) {
            if (DB::table('message_templates')->where('event', $event->value)->exists()) {
                continue;
            }

            DB::table('message_templates')->insert([
                'event' => $event->value,
                'subject' => $event->label(),
                'body' => '{summary}',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('message_deliveries', function (Blueprint $table) {
            $table->dropIndex('message_deliveries_channel_status_created');
            $table->dropIndex('message_deliveries_user_created');
            $table->dropColumn(['queue_name', 'queued_at', 'last_attempt_at']);
        });
    }
};
