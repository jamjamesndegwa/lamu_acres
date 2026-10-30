<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_policies', function (Blueprint $table) {
            $table->date('effective_from')->nullable()->after('apply_defaults');
        });

        Schema::table('agency_fee_policies', function (Blueprint $table) {
            $table->date('effective_from')->nullable()->after('apply_defaults');
        });

        DB::table('commission_policies')->where('code', 'lease_default')->update([
            'apply_defaults' => false,
            'terms' => 'Draft lease broker commission template only. Percentages shown here are not an approved tariff. Staff must propose and accept a commission agreement with an explicit calculation basis, rate or fixed amount, funding party, and lease confirmation qualifying event before any commission can be earned.',
            'updated_at' => now(),
        ]);

        DB::table('agency_fee_policies')->where('code', 'lease_default')->update([
            'apply_defaults' => false,
            'terms' => 'Draft lease agency fee template only. Percentages shown here are not an approved tariff. Staff must propose and accept an agency fee agreement with an explicit calculation basis, rate or fixed amount, fee payer, and lease confirmation qualifying event before any fee can be earned.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('agency_fee_policies', function (Blueprint $table) {
            $table->dropColumn('effective_from');
        });

        Schema::table('commission_policies', function (Blueprint $table) {
            $table->dropColumn('effective_from');
        });
    }
};
