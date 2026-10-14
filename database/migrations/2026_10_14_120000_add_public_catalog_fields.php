<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counties', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('localities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('county_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            $table->unique(['county_id', 'slug']);
        });

        Schema::table('properties', function (Blueprint $table) {
            $table->string('purpose')->default('sale')->index();
            $table->string('property_type')->default('agricultural')->index();
            $table->string('availability')->default('available')->index();
            $table->boolean('featured')->default(false)->index();
            $table->foreignId('county_id')->nullable()->after('locality')->constrained()->nullOnDelete();
            $table->foreignId('locality_id')->nullable()->after('county_id')->constrained()->nullOnDelete();
            $table->index(['status', 'availability', 'published_at']);
            $table->index('price_amount');
            $table->index('acreage');
            $table->index(['county', 'locality']);
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['status', 'availability', 'published_at']);
            $table->dropIndex(['price_amount']);
            $table->dropIndex(['acreage']);
            $table->dropIndex(['county', 'locality']);
            $table->dropConstrainedForeignId('locality_id');
            $table->dropConstrainedForeignId('county_id');
            $table->dropColumn(['purpose', 'property_type', 'availability', 'featured']);
        });

        Schema::dropIfExists('localities');
        Schema::dropIfExists('counties');
    }
};
