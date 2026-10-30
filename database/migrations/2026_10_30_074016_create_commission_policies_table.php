<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_policies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->decimal('default_rate_percent', 5, 2)->nullable();
            $table->unsignedInteger('default_fixed_amount')->nullable();
            $table->string('default_funding_party')->default('seller');
            $table->unsignedSmallInteger('default_payment_due_days')->default(14);
            $table->unsignedSmallInteger('default_protection_months')->default(6);
            $table->text('terms');
            $table->boolean('apply_defaults')->default(true);
            $table->timestamps();
        });

        DB::table('commission_policies')->insert([
            'code' => 'default',
            'name' => 'Standard broker commission',
            'default_rate_percent' => 10,
            'default_fixed_amount' => null,
            'default_funding_party' => 'seller',
            'default_payment_due_days' => 14,
            'default_protection_months' => 6,
            'terms' => 'Default broker commission is ten percent of the agreed sale price, funded by the seller, unless a signed agreement records a different rate, fixed fee, or funding party. Payment is due within the recorded number of days after the commission becomes payable.',
            'apply_defaults' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_policies');
    }
};
