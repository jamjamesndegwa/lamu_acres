<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->decimal('requested_acreage', 10, 4)->nullable()->after('amount');
            $table->foreignId('broker_introduction_id')->nullable()->after('enquiry_id')->constrained()->nullOnDelete();
            $table->foreignId('submitted_by_broker_id')->nullable()->after('broker_introduction_id')->constrained('users')->nullOnDelete();
            $table->foreignId('sales_transaction_id')->nullable()->after('submitted_by_broker_id')->constrained()->nullOnDelete();
            $table->unique('sales_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropUnique(['sales_transaction_id']);
            $table->dropConstrainedForeignId('sales_transaction_id');
            $table->dropConstrainedForeignId('submitted_by_broker_id');
            $table->dropConstrainedForeignId('broker_introduction_id');
            $table->dropColumn('requested_acreage');
        });
    }
};
