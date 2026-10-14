<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_profiles', function (Blueprint $table) {
            $table->unsignedInteger('budget_amount')->nullable()->after('preferred_locality');
            $table->decimal('min_acreage', 12, 4)->nullable()->after('budget_amount');
            $table->decimal('max_acreage', 12, 4)->nullable()->after('min_acreage');
        });
    }

    public function down(): void
    {
        Schema::table('buyer_profiles', function (Blueprint $table) {
            $table->dropColumn(['budget_amount', 'min_acreage', 'max_acreage']);
        });
    }
};
