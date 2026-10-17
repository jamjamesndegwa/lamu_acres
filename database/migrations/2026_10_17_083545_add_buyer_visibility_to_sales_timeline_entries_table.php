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
        Schema::table('sales_timeline_entries', function (Blueprint $table) {
            $table->boolean('visible_to_buyer')->default(false)->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_timeline_entries', function (Blueprint $table) {
            $table->dropIndex(['visible_to_buyer']);
            $table->dropColumn('visible_to_buyer');
        });
    }
};
