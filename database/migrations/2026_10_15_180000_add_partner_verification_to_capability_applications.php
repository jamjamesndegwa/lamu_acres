<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('capability_applications', function (Blueprint $table): void {
            $table->longText('verification')->nullable();
            $table->longText('verification_history')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('capability_applications', function (Blueprint $table): void {
            $table->dropColumn(['verification', 'verification_history']);
        });
    }
};
