<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agency_fee_agreements', function (Blueprint $table): void {
            $table->id();
            $table->string('reference');
            $table->foreignId('sales_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('fee_payer');
            $table->string('calculation_basis')->default('agreed_sale_price');
            $table->decimal('rate_percent', 5, 2)->nullable();
            $table->unsignedBigInteger('fixed_amount')->nullable();
            $table->unsignedBigInteger('earned_amount')->nullable();
            $table->unsignedBigInteger('collected_amount')->default(0);
            $table->string('qualifying_event');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->text('terms')->nullable();
            $table->string('status')->default('proposed')->index();
            $table->text('dispute_reason')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('previous_id')->nullable();
            $table->unsignedBigInteger('root_id')->nullable();
            $table->string('open_claim')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['sales_transaction_id', 'open_claim'], 'agency_fee_open_claim');
        });

        Schema::table('agency_fee_agreements', function (Blueprint $table): void {
            $table->foreign('previous_id')->references('id')->on('agency_fee_agreements')->nullOnDelete();
            $table->foreign('root_id')->references('id')->on('agency_fee_agreements')->nullOnDelete();
        });

        Schema::create('agency_fee_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('agency_fee_agreement_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('reference')->unique();
            $table->string('status')->default('initiated');
            $table->string('reconciliation')->default('unmatched');
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_fee_receipts');
        Schema::table('agency_fee_agreements', function (Blueprint $table): void {
            $table->dropForeign(['previous_id']);
            $table->dropForeign(['root_id']);
        });
        Schema::dropIfExists('agency_fee_agreements');
    }
};
