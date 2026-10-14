<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counties', function (Blueprint $table) {
            $table->string('code', 8)->nullable()->unique();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('boundary_wkt')->nullable();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::table('localities', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('county_id')->constrained('localities')->nullOnDelete();
            $table->string('kind')->default('place')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('boundary_wkt')->nullable();
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::create('locality_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('locality_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['locality_id', 'slug']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->text('description')->nullable();
            $table->string('land_use')->nullable()->index();
            $table->decimal('public_latitude', 10, 7)->nullable();
            $table->decimal('public_longitude', 10, 7)->nullable();
            $table->decimal('restricted_latitude', 10, 7)->nullable();
            $table->decimal('restricted_longitude', 10, 7)->nullable();
            $table->string('marketing_role')->nullable();
            $table->foreignId('represented_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('portion_label')->nullable();
        });

        Schema::create('property_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['property_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_audits');

        Schema::table('properties', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('represented_by');
            $table->dropColumn([
                'description',
                'land_use',
                'public_latitude',
                'public_longitude',
                'restricted_latitude',
                'restricted_longitude',
                'marketing_role',
                'portion_label',
            ]);
        });

        Schema::dropIfExists('locality_aliases');

        Schema::table('localities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['kind', 'latitude', 'longitude', 'boundary_wkt', 'is_active']);
        });

        Schema::table('counties', function (Blueprint $table) {
            $table->dropColumn(['code', 'latitude', 'longitude', 'boundary_wkt', 'is_active']);
        });
    }
};
