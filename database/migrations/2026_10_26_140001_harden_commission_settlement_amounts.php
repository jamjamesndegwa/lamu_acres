<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('commission_agreements', 'paid_amount')) {
            Schema::table('commission_agreements', function (Blueprint $table): void {
                $table->unsignedBigInteger('paid_amount')->default(0)->after('earned_amount');
            });
        }

        $hasAgreementUnique = collect(Schema::getIndexes('commission_payments'))
            ->contains(fn (array $index): bool => ($index['name'] ?? '') === 'commission_payment_agreement');

        if ($hasAgreementUnique) {
            Schema::table('commission_payments', function (Blueprint $table): void {
                $table->dropForeign(['commission_agreement_id']);
                $table->dropUnique('commission_payment_agreement');
                $table->foreign('commission_agreement_id')
                    ->references('id')
                    ->on('commission_agreements')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        $hasAgreementUnique = collect(Schema::getIndexes('commission_payments'))
            ->contains(fn (array $index): bool => ($index['name'] ?? '') === 'commission_payment_agreement');

        if (! $hasAgreementUnique) {
            Schema::table('commission_payments', function (Blueprint $table): void {
                $table->dropForeign(['commission_agreement_id']);
                $table->unique('commission_agreement_id', 'commission_payment_agreement');
                $table->foreign('commission_agreement_id')
                    ->references('id')
                    ->on('commission_agreements')
                    ->cascadeOnDelete();
            });
        }

        if (Schema::hasColumn('commission_agreements', 'paid_amount')) {
            Schema::table('commission_agreements', function (Blueprint $table): void {
                $table->dropColumn('paid_amount');
            });
        }
    }
};
