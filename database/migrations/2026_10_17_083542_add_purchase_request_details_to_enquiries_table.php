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
        Schema::table('enquiries', function (Blueprint $table) {
            $table->boolean('purchase_request')->default(false)->index();
            $table->unsignedBigInteger('requested_price')->nullable();
            $table->decimal('requested_acreage', 12, 4)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropIndex(['purchase_request']);
            $table->dropColumn(['purchase_request', 'requested_price', 'requested_acreage']);
        });
    }
};
