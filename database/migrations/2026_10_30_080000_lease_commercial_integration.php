<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->string('deal_kind')->default('sale')->after('reference');
            $table->date('lease_starts_on')->nullable()->after('completed_at');
            $table->date('lease_ends_on')->nullable()->after('lease_starts_on');
            $table->string('rent_frequency')->nullable()->after('lease_ends_on');
            $table->unsignedBigInteger('agreed_rent')->nullable()->after('agreed_price');
            $table->unsignedBigInteger('lease_premium')->nullable()->after('agreed_rent');
            $table->foreignId('property_transaction_id')->nullable()->after('lease_premium')->constrained('property_transactions')->nullOnDelete();
        });

        Schema::table('property_transactions', function (Blueprint $table) {
            $table->foreignId('sales_transaction_id')->nullable()->unique()->after('property_id')->constrained()->nullOnDelete();
        });

        Schema::table('commission_agreements', function (Blueprint $table) {
            $table->string('calculation_basis')->nullable()->after('fixed_amount');
            $table->decimal('monthly_rent_multiplier', 8, 2)->nullable()->after('rate_percent');
            $table->json('calculation_snapshot')->nullable()->after('earned_amount');
        });

        Schema::table('agency_fee_agreements', function (Blueprint $table) {
            $table->decimal('monthly_rent_multiplier', 8, 2)->nullable()->after('rate_percent');
            $table->json('calculation_snapshot')->nullable()->after('earned_amount');
        });

        Schema::table('commission_policies', function (Blueprint $table) {
            $table->string('default_calculation_basis')->nullable()->after('default_fixed_amount');
            $table->decimal('default_monthly_rent_multiplier', 8, 2)->nullable()->after('default_rate_percent');
        });

        DB::table('commission_policies')->where('code', 'default')->update([
            'default_calculation_basis' => 'agreed_sale_price',
        ]);

        DB::table('commission_policies')->insert([
            'code' => 'lease_default',
            'name' => 'Standard lease broker commission',
            'default_calculation_basis' => 'first_year_rent_percent',
            'default_rate_percent' => 15,
            'default_fixed_amount' => null,
            'default_monthly_rent_multiplier' => null,
            'default_funding_party' => 'lessor',
            'default_payment_due_days' => 14,
            'default_protection_months' => 6,
            'terms' => 'Default lease broker commission is fifteen percent of the first year of contracted rent, funded by the lessor, unless a signed agreement records a different basis, rate, fixed fee, or funding party.',
            'apply_defaults' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('agency_fee_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('default_calculation_basis')->default('agreed_sale_price');
            $table->decimal('default_rate_percent', 5, 2)->nullable();
            $table->unsignedInteger('default_fixed_amount')->nullable();
            $table->decimal('default_monthly_rent_multiplier', 8, 2)->nullable();
            $table->string('default_fee_payer')->default('seller');
            $table->text('terms');
            $table->boolean('apply_defaults')->default(true);
            $table->timestamps();
        });

        DB::table('agency_fee_policies')->insert([
            [
                'code' => 'default',
                'name' => 'Standard sale agency fee',
                'default_calculation_basis' => 'agreed_sale_price',
                'default_rate_percent' => 2.5,
                'default_fixed_amount' => null,
                'default_monthly_rent_multiplier' => null,
                'default_fee_payer' => 'seller',
                'terms' => 'Default agency fee on completed sales is two and a half percent of the agreed sale price unless an accepted agreement records otherwise.',
                'apply_defaults' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'code' => 'lease_default',
                'name' => 'Standard lease agency fee',
                'default_calculation_basis' => 'first_year_rent_percent',
                'default_rate_percent' => 10,
                'default_fixed_amount' => null,
                'default_monthly_rent_multiplier' => null,
                'default_fee_payer' => 'lessor',
                'terms' => 'Default lease agency fee is ten percent of the first year of contracted rent unless an accepted agreement records otherwise.',
                'apply_defaults' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_fee_policies');

        Schema::table('commission_policies', function (Blueprint $table) {
            $table->dropColumn(['default_calculation_basis', 'default_monthly_rent_multiplier']);
        });

        DB::table('commission_policies')->where('code', 'lease_default')->delete();

        Schema::table('agency_fee_agreements', function (Blueprint $table) {
            $table->dropColumn(['monthly_rent_multiplier', 'calculation_snapshot']);
        });

        Schema::table('commission_agreements', function (Blueprint $table) {
            $table->dropColumn(['calculation_basis', 'monthly_rent_multiplier', 'calculation_snapshot']);
        });

        Schema::table('property_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_transaction_id');
        });

        Schema::table('sales_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('property_transaction_id');
            $table->dropColumn([
                'deal_kind',
                'lease_starts_on',
                'lease_ends_on',
                'rent_frequency',
                'agreed_rent',
                'lease_premium',
            ]);
        });
    }
};
