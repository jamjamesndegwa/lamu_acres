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
        $legacy = [
            MessageEvent::PasswordChanged,
            MessageEvent::AccountSuspended,
            MessageEvent::AccountRestored,
            MessageEvent::ApplicationSubmitted,
            MessageEvent::ApplicationResubmitted,
            MessageEvent::ApplicationStaffAlert,
            MessageEvent::ListingSubmitted,
            MessageEvent::ListingStaffAlert,
            MessageEvent::ListingInformationRequested,
            MessageEvent::ListingRejected,
            MessageEvent::PropertyPublished,
            MessageEvent::PropertyUnpublished,
            MessageEvent::ListingMaterialChange,
            MessageEvent::ListingInventoryImpact,
        ];

        DB::table('message_templates')->whereIn('event', array_map(
            fn (MessageEvent $event): string => $event->value,
            $legacy,
        ))->delete();
    }
};
