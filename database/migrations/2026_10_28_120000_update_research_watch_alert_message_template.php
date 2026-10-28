<?php

use App\Enums\MessageEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $event = MessageEvent::ResearchWatchAlert->value;
        $subject = 'Lamu Acres — Research watch update';
        $body = <<<'BODY'
Hello {name},

A {status} matching your Investor Pass watchlist has been published.

Title: {title}
Watch: {watch}
Reference: {reference}

{summary}

Open the authorized research in your Investor workspace:
{url}

Optional research updates respect your email preferences. Security and subscription notices are always sent separately.
BODY;

        if (DB::table('message_templates')->where('event', $event)->exists()) {
            DB::table('message_templates')->where('event', $event)->update([
                'subject' => $subject,
                'body' => $body,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('message_templates')->insert([
            'event' => $event,
            'subject' => $subject,
            'body' => $body,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        DB::table('message_templates')->where('event', MessageEvent::ResearchWatchAlert->value)->update([
            'subject' => MessageEvent::ResearchWatchAlert->label(),
            'body' => '{title} {summary}',
            'updated_at' => now(),
        ]);
    }
};
