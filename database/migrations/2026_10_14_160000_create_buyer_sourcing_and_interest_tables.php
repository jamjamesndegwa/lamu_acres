<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->json('amenities')->nullable();
        });

        Schema::create('sourcing_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('submitted')->index();
            $table->string('location_label')->nullable();
            $table->decimal('centre_latitude', 10, 7)->nullable();
            $table->decimal('centre_longitude', 10, 7)->nullable();
            $table->decimal('radius_km', 8, 2)->nullable();
            $table->string('centre_wkt', 120)->nullable();
            $table->text('boundary_wkt')->nullable();
            $table->json('boundary_points')->nullable();
            $table->decimal('min_acreage', 12, 4)->nullable();
            $table->decimal('max_acreage', 12, 4)->nullable();
            $table->unsignedBigInteger('maximum_budget')->nullable();
            $table->string('intended_use')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sourcing_requests ADD centre_point GEOMETRY NULL');
            DB::statement('ALTER TABLE sourcing_requests ADD search_area GEOMETRY NULL');
        }

        Schema::create('sourcing_request_amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sourcing_request_id')->constrained()->cascadeOnDelete();
            $table->string('amenity');
            $table->boolean('required')->default(false);
            $table->timestamps();

            $table->unique(['sourcing_request_id', 'amenity']);
        });

        Schema::create('sourcing_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sourcing_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('band')->index();
            $table->decimal('distance_km', 8, 3)->nullable();
            $table->json('reasons')->nullable();
            $table->timestamps();

            $table->unique(['sourcing_request_id', 'property_id']);
        });

        Schema::create('sourcing_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sourcing_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('summary');
            $table->boolean('visible_to_buyer')->default(true);
            $table->timestamps();
        });

        Schema::create('property_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sourcing_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('enquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('shortlisted')->index();
            $table->text('buyer_feedback')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['buyer_id', 'property_id']);
        });

        Schema::create('site_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('requested')->index();
            $table->timestamp('requested_for');
            $table->timestamp('confirmed_for')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('buyer_acknowledged_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_match_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('enquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('currency', 3)->default('KES');
            $table->text('message')->nullable();
            $table->string('status')->default('submitted')->index();
            $table->timestamps();
        });

        Schema::create('document_access_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_match_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('requested')->index();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('buyer_timeline_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sourcing_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('property_match_id')->nullable()->constrained()->nullOnDelete();
            $table->string('summary');
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['buyer_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buyer_timeline_entries');
        Schema::dropIfExists('document_access_requests');
        Schema::dropIfExists('offers');
        Schema::dropIfExists('site_visits');
        Schema::dropIfExists('property_matches');
        Schema::dropIfExists('sourcing_activities');
        Schema::dropIfExists('sourcing_matches');
        Schema::dropIfExists('sourcing_request_amenities');

        if (Schema::getConnection()->getDriverName() === 'mysql' && Schema::hasTable('sourcing_requests')) {
            DB::statement('ALTER TABLE sourcing_requests DROP COLUMN centre_point, DROP COLUMN search_area');
        }

        Schema::dropIfExists('sourcing_requests');

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('amenities');
        });
    }
};
