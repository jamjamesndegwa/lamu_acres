<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('checkout_amount')->nullable();
            $table->string('checkout_currency', 3)->nullable();
            $table->text('checkout_policy')->nullable();
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('number')->unique();
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('status')->default('open');
            $table->text('policy');
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('subscription_invoices')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('idempotency_key')->unique();
            $table->string('phone');
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->string('status');
            $table->string('merchant_request_id')->nullable();
            $table->string('checkout_request_id')->nullable()->unique();
            $table->string('provider_result_code')->nullable();
            $table->string('provider_result_desc')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('reconciled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->unique()->constrained('subscription_payments')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('subscription_invoices')->cascadeOnDelete();
            $table->string('provider_receipt')->unique();
            $table->unsignedInteger('amount');
            $table->string('currency', 3);
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('service_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('days');
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('research_assignments', function (Blueprint $table) {
            $table->id();
            $table->string('commitment');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('due_on');
            $table->string('status');
            $table->nullableMorphs('subject');
            $table->text('note')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['commitment', 'period_start'], 'research_assignment_period_unique');
        });

        Schema::create('subscriber_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('development_project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('publication_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('subject');
            $table->foreignId('research_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('delivery_key')->unique();
            $table->timestamp('delivered_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_deliveries');
        Schema::dropIfExists('subscriber_watches');
        Schema::dropIfExists('research_assignments');
        Schema::dropIfExists('service_credits');
        Schema::dropIfExists('subscription_receipts');
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('subscription_invoices');

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['checkout_amount', 'checkout_currency', 'checkout_policy']);
        });
    }
};
