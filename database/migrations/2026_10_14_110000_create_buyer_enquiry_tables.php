<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('county')->nullable();
            $table->string('locality')->nullable();
            $table->decimal('acreage', 12, 4)->nullable();
            $table->unsignedBigInteger('price_amount')->nullable();
            $table->string('price_currency', 3)->default('KES');
            $table->text('summary')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('saved_properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'property_id']);
        });

        Schema::create('broker_introductions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('broker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active')->index();
            $table->timestamp('introduced_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'property_id', 'status']);
        });

        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('broker_introduction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('new')->index();
            $table->string('lead_status')->default('new')->index();
            $table->text('message');
            $table->string('referrer_url', 2048)->nullable();
            $table->string('campaign')->nullable();
            $table->string('source')->default('website');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('flagged_as_spam')->default(false);
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'property_id']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('enquiry_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->boolean('visible_to_buyer')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_responses');
        Schema::dropIfExists('enquiries');
        Schema::dropIfExists('broker_introductions');
        Schema::dropIfExists('saved_properties');
        Schema::dropIfExists('properties');
    }
};
