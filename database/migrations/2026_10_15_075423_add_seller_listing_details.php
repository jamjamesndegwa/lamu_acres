<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('property_type')->nullable()->default('agricultural')->change();
            $table->string('price_basis')->default('total');
            $table->unsignedBigInteger('price_rate')->nullable();
            $table->boolean('price_negotiable')->default(false);
            $table->decimal('offered_acreage', 12, 4)->nullable();
            $table->decimal('minimum_acreage', 12, 4)->nullable();
            $table->string('tenure')->nullable();
            $table->string('ownership_structure')->nullable();
            $table->string('occupation_status')->nullable();
            $table->date('available_from')->nullable();
            $table->string('landmark')->nullable();
            $table->text('access_description')->nullable();
            $table->string('parcel_reference_type')->nullable();
            $table->string('survey_reference')->nullable();
            $table->string('subdivision_status')->nullable();
            $table->string('boundary_status')->nullable();
            $table->unsignedSmallInteger('lease_min_months')->nullable();
            $table->unsignedSmallInteger('lease_max_months')->nullable();
            $table->json('listing_terms')->nullable();
            $table->timestamp('submitted_at')->nullable();
        });

        Schema::table('ownership_documents', function (Blueprint $table) {
            $table->string('original_name')->nullable()->change();
            $table->string('path')->nullable()->change();
            $table->string('document_type')->default('other')->index();
            $table->string('state')->default('received')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->date('expires_at')->nullable();
        });

        Schema::table('marketing_authorities', function (Blueprint $table) {
            $table->string('scope')->default('both');
            $table->date('valid_until')->nullable();
        });

        Schema::table('property_transactions', function (Blueprint $table) {
            $table->string('kind')->default('sale')->index();
            $table->decimal('acreage', 12, 4)->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->text('terms')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('ended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('property_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by');
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'acreage', 'starts_on', 'ends_on', 'terms', 'confirmed_at', 'ended_at']);
        });

        Schema::table('marketing_authorities', function (Blueprint $table) {
            $table->dropColumn(['scope', 'valid_until']);
        });

        Schema::table('ownership_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropIndex(['document_type']);
            $table->dropIndex(['state']);
            $table->dropColumn(['document_type', 'state', 'reviewed_at', 'review_note', 'expires_at']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn([
                'price_basis',
                'price_rate',
                'price_negotiable',
                'offered_acreage',
                'minimum_acreage',
                'tenure',
                'ownership_structure',
                'occupation_status',
                'available_from',
                'landmark',
                'access_description',
                'parcel_reference_type',
                'survey_reference',
                'subdivision_status',
                'boundary_status',
                'lease_min_months',
                'lease_max_months',
                'listing_terms',
                'submitted_at',
            ]);
            $table->string('property_type')->nullable(false)->default('agricultural')->change();
        });
    }
};
