<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->foreignId('sales_transaction_id')
                ->nullable()
                ->unique()
                ->after('parent_id')
                ->constrained('sales_transactions')
                ->nullOnDelete();
        });

        Schema::table('enquiries', function (Blueprint $table): void {
            $table->string('sale_mode_at_request', 16)->nullable()->after('requested_acreage');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sales_transaction_id');
        });

        Schema::table('enquiries', function (Blueprint $table): void {
            $table->dropColumn('sale_mode_at_request');
        });
    }
};
