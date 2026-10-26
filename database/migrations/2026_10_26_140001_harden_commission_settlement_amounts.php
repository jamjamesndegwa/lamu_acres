<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_agreements', function (Blueprint $table): void {
            $table->unsignedBigInteger('paid_amount')->default(0)->after('earned_amount');
        });

        Schema::table('commission_payments', function (Blueprint $table): void {
            $table->dropUnique('commission_payment_agreement');
        });
    }

    public function down(): void
    {
        Schema::table('commission_payments', function (Blueprint $table): void {
            $table->unique('commission_agreement_id', 'commission_payment_agreement');
        });

        Schema::table('commission_agreements', function (Blueprint $table): void {
            $table->dropColumn('paid_amount');
        });
    }
};
