<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_phone', 32)->nullable()->after('phone');
            $table->boolean('notify_by_email')->default(true)->after('pending_phone');
            $table->timestamp('suspended_at')->nullable()->index();
            $table->string('suspension_reason', 500)->nullable();
        });

        Schema::create('buyer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('looking_for');
            $table->string('preferred_locality', 120)->nullable();
            $table->timestamps();
        });

        Schema::create('capability_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('capability');
            $table->string('status')->default('pending');
            $table->string('organization', 160)->nullable();
            $table->text('statement');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'capability']);
            $table->index(['status', 'capability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capability_applications');
        Schema::dropIfExists('buyer_profiles');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pending_phone', 'notify_by_email', 'suspended_at', 'suspension_reason']);
        });
    }
};
