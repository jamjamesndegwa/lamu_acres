<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('property_media', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_cover')->default(false);
            $table->string('public_disk')->nullable();
            $table->string('public_path')->nullable();
            $table->string('video_url')->nullable();
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->string('road_access')->default('unknown');
            $table->string('verification_level')->default('unreviewed');
            $table->timestamp('last_reviewed_at')->nullable();
            $table->text('infrastructure_note')->nullable();
        });

        Schema::create('property_amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('amenity');
            $table->string('state')->default('unknown');
            $table->timestamps();

            $table->unique(['property_id', 'amenity']);
        });

        Schema::create('property_highlights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('body');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('property_price_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('price_amount');
            $table->string('price_currency', 3)->default('KES');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('property_availability_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->string('availability');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('property_document_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('state')->default('unknown');
            $table->timestamps();

            $table->unique(['property_id', 'code']);
        });

        Schema::create('property_visit_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->date('preferred_on');
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('listing_document_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_document_requests');
        Schema::dropIfExists('property_visit_requests');
        Schema::dropIfExists('property_document_checks');
        Schema::dropIfExists('property_availability_events');
        Schema::dropIfExists('property_price_changes');
        Schema::dropIfExists('property_highlights');
        Schema::dropIfExists('property_amenities');

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn(['road_access', 'verification_level', 'last_reviewed_at', 'infrastructure_note']);
        });

        Schema::table('property_media', function (Blueprint $table) {
            $table->dropColumn(['position', 'is_cover', 'public_disk', 'public_path', 'video_url']);
        });
    }
};
