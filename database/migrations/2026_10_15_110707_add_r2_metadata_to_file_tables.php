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
        Schema::table('ownership_documents', function (Blueprint $table) {
            $table->string('mime')->nullable()->after('disk');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime');
        });

        Schema::table('evidence_files', function (Blueprint $table) {
            $table->string('mime')->nullable()->after('disk');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime');
        });

        Schema::table('intelligence_files', function (Blueprint $table) {
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime');
        });

        Schema::table('property_media', function (Blueprint $table) {
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime');
        });

        Schema::table('broker_introductions', function (Blueprint $table) {
            $table->string('consent_disk')->default('local')->after('consent_path');
            $table->string('evidence_disk')->default('local')->after('evidence_path');
        });
    }

    public function down(): void
    {
        Schema::table('broker_introductions', function (Blueprint $table) {
            $table->dropColumn(['consent_disk', 'evidence_disk']);
        });

        Schema::table('property_media', function (Blueprint $table) {
            $table->dropColumn('size_bytes');
        });

        Schema::table('intelligence_files', function (Blueprint $table) {
            $table->dropColumn('size_bytes');
        });

        Schema::table('evidence_files', function (Blueprint $table) {
            $table->dropColumn(['mime', 'size_bytes']);
        });

        Schema::table('ownership_documents', function (Blueprint $table) {
            $table->dropColumn(['mime', 'size_bytes']);
        });
    }
};
