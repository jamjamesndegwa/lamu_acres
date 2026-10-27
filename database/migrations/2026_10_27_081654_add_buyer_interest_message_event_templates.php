<?php

use App\Enums\MessageEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (MessageEvent::cases() as $event) {
            if (DB::table('message_templates')->where('event', $event->value)->exists()) {
                continue;
            }

            DB::table('message_templates')->insert([
                'event' => $event->value,
                'subject' => 'Lamu Acres — '.$event->label(),
                'body' => "Hello {name},\n\n{title}\n\n{summary}\n\n{url}",
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Templates are shared across releases; no rollback.
    }
};
