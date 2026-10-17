<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table): void {
            $table->timestamp('buyer_confirmed_at')->nullable();
            $table->timestamp('reschedule_requested_at')->nullable();
            $table->timestamp('reschedule_preferred_for')->nullable();
            $table->text('reschedule_note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table): void {
            $table->dropColumn([
                'buyer_confirmed_at',
                'reschedule_requested_at',
                'reschedule_preferred_for',
                'reschedule_note',
            ]);
        });
    }
};
