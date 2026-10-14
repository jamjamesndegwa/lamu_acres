<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sourcing_matches', function (Blueprint $table) {
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignId('dismissed_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sourcing_matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dismissed_by');
            $table->dropColumn('dismissed_at');
        });
    }
};
