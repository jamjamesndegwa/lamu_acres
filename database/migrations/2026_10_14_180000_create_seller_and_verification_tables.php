<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('legal_name');
            $table->string('correspondence')->nullable();
            $table->text('holding_note')->nullable();
            $table->timestamps();
        });

        Schema::create('property_owners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('relationship');
            $table->string('status')->default('pending')->index();
            $table->decimal('share_percent', 5, 2)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['property_id', 'user_id']);
        });

        Schema::create('ownership_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('disk')->default('local');
            $table->timestamps();
        });

        Schema::create('marketing_authorities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('submitted')->index();
            $table->text('terms');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_instructions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind');
            $table->text('body');
            $table->string('requested_availability')->nullable();
            $table->timestamps();
        });

        Schema::create('seller_enquiry_briefings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_by')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('offer_presentations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('presented_by')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->text('summary');
            $table->string('seller_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('property_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('instructed')->index();
            $table->unsignedBigInteger('amount')->nullable();
            $table->text('statement')->nullable();
            $table->timestamps();
        });

        Schema::create('verification_passports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('level')->default('advertised');
            $table->string('override')->default('none');
            $table->text('critical_exception')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('verification_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('category');
            $table->string('state')->default('not_supplied')->index();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('evidence_note')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('review_due_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['property_id', 'category']);
        });

        Schema::create('evidence_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verification_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('sensitivity')->default('general');
            $table->string('original_name');
            $table->string('path');
            $table->string('disk')->default('local');
            $table->timestamps();
        });

        Schema::create('evidence_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();
        });

        Schema::create('evidence_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_file_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome');
            $table->timestamp('accessed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_access_logs');
        Schema::dropIfExists('evidence_grants');
        Schema::dropIfExists('evidence_files');
        Schema::dropIfExists('verification_items');
        Schema::dropIfExists('verification_passports');
        Schema::dropIfExists('property_transactions');
        Schema::dropIfExists('offer_presentations');
        Schema::dropIfExists('seller_enquiry_briefings');
        Schema::dropIfExists('sale_instructions');
        Schema::dropIfExists('marketing_authorities');
        Schema::dropIfExists('ownership_documents');
        Schema::dropIfExists('property_owners');
        Schema::dropIfExists('seller_profiles');
    }
};
