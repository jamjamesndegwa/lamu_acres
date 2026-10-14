<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('map_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('county_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kind');
            $table->string('source');
            $table->text('description')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('boundary_wkt')->nullable();
            $table->boolean('is_visible')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('infrastructure_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('county_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('kind');
            $table->string('source');
            $table->text('notes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('boundary_wkt')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $now = now();

        DB::table('map_layers')->insert([
            [
                'name' => 'County boundaries',
                'slug' => 'county-boundaries',
                'kind' => 'county_boundaries',
                'source' => 'Stored geography records',
                'description' => 'Draws a county only when a verified boundary is already stored on that county.',
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Named places',
                'slug' => 'named-places',
                'kind' => 'locality_points',
                'source' => 'Stored geography records',
                'description' => 'Draws a marker only where a locality already has a latitude and longitude. A marker is not an administrative boundary.',
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Locality boundaries',
                'slug' => 'locality-boundaries',
                'kind' => 'locality_boundaries',
                'source' => 'Stored geography records',
                'description' => 'Draws a ward or place only when a verified boundary is already stored.',
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Infrastructure reference',
                'slug' => 'infrastructure-reference',
                'kind' => 'infrastructure',
                'source' => 'Infrastructure reference records',
                'description' => 'Draws a road, water point, jetty, or other reference only when coordinates or a verified line are stored.',
                'is_visible' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('infrastructure_features');
        Schema::dropIfExists('map_layers');
    }
};
