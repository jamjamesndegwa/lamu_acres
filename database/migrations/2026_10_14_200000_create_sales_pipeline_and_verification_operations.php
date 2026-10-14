<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verification_passports', function (Blueprint $table) {
            $table->foreignId('assigned_to')->nullable()->after('suspended_at')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->timestamp('deadline_at')->nullable()->after('assigned_by');
        });

        Schema::create('verification_review_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('verification_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision');
            $table->text('reason');
            $table->timestamps();

            $table->index(['property_id', 'id'], 'verification_logs_property');
        });

        Schema::create('sales_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origin');
            $table->foreignId('enquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sourcing_request_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('broker_introduction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sale_instruction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('stage')->default('enquiry')->index();
            $table->string('outcome')->nullable()->index();
            $table->unsignedBigInteger('agreed_price')->nullable();
            $table->decimal('acreage', 12, 4)->nullable();
            $table->string('currency', 3)->default('KES');
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_offer_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('seller_response')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('sales_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('due_on')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_document_refs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('reference');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::create('sales_timeline_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('summary');
            $table->timestamps();
        });

        Schema::create('closing_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->string('decision');
            $table->text('reason');
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });

        Schema::create('closing_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('state')->default('missing');
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sales_transaction_id', 'code'], 'closing_evidence_tx_code');
        });

        Schema::create('commission_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('reference');
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('broker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('buyer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->decimal('rate_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('fixed_amount')->nullable();
            $table->unsignedBigInteger('earned_amount')->nullable();
            $table->string('funding_party');
            $table->timestamp('protection_starts_at')->nullable();
            $table->timestamp('protection_ends_at')->nullable();
            $table->string('qualifying_event');
            $table->unsignedSmallInteger('payment_due_days')->default(14);
            $table->string('status')->default('proposed')->index();
            $table->text('dispute_reason')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('previous_id')->nullable();
            $table->unsignedBigInteger('root_id')->nullable();
            $table->string('open_claim')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sales_transaction_id', 'broker_id', 'open_claim'], 'commission_open_claim');
        });

        Schema::table('commission_agreements', function (Blueprint $table) {
            $table->foreign('previous_id')->references('id')->on('commission_agreements')->nullOnDelete();
            $table->foreign('root_id')->references('id')->on('commission_agreements')->nullOnDelete();
        });

        Schema::create('commission_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_agreement_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('reference')->unique();
            $table->string('status')->default('initiated');
            $table->string('reconciliation')->default('unmatched');
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique('commission_agreement_id', 'commission_payment_agreement');
        });

        Schema::create('sales_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('commission_agreement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['sales_transaction_id', 'id'], 'sales_audits_tx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_audits');
        Schema::dropIfExists('commission_payments');
        Schema::table('commission_agreements', function (Blueprint $table) {
            $table->dropForeign(['previous_id']);
            $table->dropForeign(['root_id']);
        });
        Schema::dropIfExists('commission_agreements');
        Schema::dropIfExists('closing_evidence');
        Schema::dropIfExists('closing_reviews');
        Schema::dropIfExists('sales_timeline_entries');
        Schema::dropIfExists('sales_document_refs');
        Schema::dropIfExists('sales_milestones');
        Schema::dropIfExists('sales_offer_entries');
        Schema::dropIfExists('sales_transactions');
        Schema::dropIfExists('verification_review_logs');

        Schema::table('verification_passports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('assigned_by');
            $table->dropColumn('deadline_at');
        });
    }
};
