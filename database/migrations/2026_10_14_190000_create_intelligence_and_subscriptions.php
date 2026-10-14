<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('development_projects', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('category');
            $table->string('project_status');
            $table->string('coverage');
            $table->foreignId('county_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained()->nullOnDelete();
            $table->text('summary');
            $table->text('research_notes')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('boundary_wkt')->nullable();
            $table->string('source_name');
            $table->string('source_url')->nullable();
            $table->date('source_published_on')->nullable();
            $table->date('verified_on')->nullable();
            $this->publication($table);
            $table->timestamps();
        });

        Schema::create('development_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('development_project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->date('occurred_on')->nullable();
            $table->text('note')->nullable();
            $table->string('source_name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('imagery_scenes', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('provider');
            $table->date('acquired_on');
            $table->string('resolution');
            $table->string('coverage');
            $table->string('cloud_conditions')->nullable();
            $table->string('license_name');
            $table->boolean('redistribution_allowed')->default(false);
            $table->string('source_url')->nullable();
            $table->foreignId('county_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('development_project_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('notes')->nullable();
            $this->publication($table);
            $table->timestamps();
        });

        Schema::create('imagery_comparisons', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('slug')->unique();
            $table->string('area_label');
            $table->foreignId('before_scene_id')->constrained('imagery_scenes')->restrictOnDelete();
            $table->foreignId('after_scene_id')->constrained('imagery_scenes')->restrictOnDelete();
            $table->foreignId('development_project_id')->nullable()->constrained()->nullOnDelete();
            $table->text('observed_change');
            $table->text('annotation')->nullable();
            $this->publication($table);
            $table->timestamps();
        });

        Schema::create('planning_records', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('area');
            $table->foreignId('county_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained()->nullOnDelete();
            $table->string('instrument_status');
            $table->text('permitted_uses')->nullable();
            $table->text('restrictions')->nullable();
            $table->date('effective_on')->nullable();
            $table->string('source_document');
            $table->string('source_url')->nullable();
            $table->string('map_reference')->nullable();
            $table->text('notes')->nullable();
            $this->publication($table);
            $table->timestamps();
        });

        Schema::create('verified_sales', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('locality_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('acreage', 12, 4);
            $table->unsignedBigInteger('price_amount');
            $table->string('price_currency')->default('KES');
            $table->date('sold_on');
            $table->string('source_name');
            $table->text('note')->nullable();
            $this->publication($table);
            $table->timestamps();
        });

        Schema::create('research_reports', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('summary');
            $table->text('body');
            $table->date('report_date')->nullable();
            $this->publication($table);
            $table->timestamps();
        });

        Schema::create('research_citations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_report_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('url')->nullable();
            $table->date('retrieved_on')->nullable();
            $table->timestamps();
        });

        Schema::create('report_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('development_project_id')->constrained()->cascadeOnDelete();
            $table->unique(['research_report_id', 'development_project_id'], 'report_project_unique');
        });

        Schema::create('report_localities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('research_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('locality_id')->constrained()->cascadeOnDelete();
            $table->unique(['research_report_id', 'locality_id'], 'report_locality_unique');
        });

        Schema::create('intelligence_files', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->string('kind');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('intelligence_audits', function (Blueprint $table) {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('from_publication')->nullable();
            $table->string('to_publication')->nullable();
            $table->text('note')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id', 'created_at'], 'intel_audit_subject_idx');
        });

        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3);
            $table->string('interval');
            $table->text('terms');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->constrained()->cascadeOnDelete();
            $table->string('status');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'subscription_plan_id'], 'subscription_user_plan_unique');
        });

        Schema::create('subscription_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->unsignedInteger('amount_cents')->nullable();
            $table->string('currency', 3)->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::table('subscription_plans')->insert([
            'code' => 'investor_pass',
            'name' => 'Lamu Investor Pass',
            'amount_cents' => 10000,
            'currency' => 'USD',
            'interval' => 'year',
            'terms' => 'Annual access to published investor research. This application does not collect payment. A finance officer records the membership before access starts.',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_events');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('intelligence_audits');
        Schema::dropIfExists('intelligence_files');
        Schema::dropIfExists('report_localities');
        Schema::dropIfExists('report_projects');
        Schema::dropIfExists('research_citations');
        Schema::dropIfExists('research_reports');
        Schema::dropIfExists('verified_sales');
        Schema::dropIfExists('planning_records');
        Schema::dropIfExists('imagery_comparisons');
        Schema::dropIfExists('imagery_scenes');
        Schema::dropIfExists('development_milestones');
        Schema::dropIfExists('development_projects');
    }

    private function publication(Blueprint $table): void
    {
        $table->string('publication_status')->default('draft');
        $table->timestamp('published_at')->nullable();
        $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
        $table->index('publication_status', 'pub_'.$table->getTable());
    }
};
