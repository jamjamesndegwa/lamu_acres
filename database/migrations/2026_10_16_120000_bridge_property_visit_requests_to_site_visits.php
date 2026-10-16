<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table): void {
            $table->foreignId('property_visit_request_id')
                ->nullable()
                ->unique()
                ->after('property_match_id')
                ->constrained('property_visit_requests')
                ->nullOnDelete();
            $table->foreignId('property_match_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('property_visit_request_id');
            $table->foreignId('property_match_id')->nullable(false)->change();
        });
    }
};
