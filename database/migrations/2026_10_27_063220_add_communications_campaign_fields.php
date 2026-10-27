<?php

use App\Enums\MessageEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('marketing_email_consent')->default(false)->after('notify_by_email');
            $table->boolean('marketing_sms_consent')->default(false)->after('marketing_email_consent');
            $table->string('communications_unsubscribe_token', 64)->nullable()->unique()->after('marketing_sms_consent');
        });

        DB::table('users')
            ->whereNull('communications_unsubscribe_token')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $user): void {
                DB::table('users')->where('id', $user->id)->update([
                    'communications_unsubscribe_token' => Str::random(40),
                ]);
            });

        Schema::create('message_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('channel');
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('draft');
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('message_deliveries', function (Blueprint $table) {
            $table->string('provider_reference')->nullable()->after('destination');
            $table->foreignId('message_campaign_id')->nullable()->after('provider_reference')->constrained()->nullOnDelete();
        });

        $now = now();

        if (! DB::table('message_templates')->where('event', MessageEvent::MarketingBroadcast->value)->exists()) {
            DB::table('message_templates')->insert([
                'event' => MessageEvent::MarketingBroadcast->value,
                'subject' => MessageEvent::MarketingBroadcast->label(),
                'body' => '{summary}',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('message_deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('message_campaign_id');
            $table->dropColumn('provider_reference');
        });

        Schema::dropIfExists('message_campaigns');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'marketing_email_consent',
                'marketing_sms_consent',
                'communications_unsubscribe_token',
            ]);
        });

        DB::table('message_templates')->where('event', MessageEvent::MarketingBroadcast->value)->delete();
    }
};
