<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            if (! Schema::hasColumn('properties', 'parcel_identifier')) {
                $table->string('parcel_identifier')->nullable()->index();
            }
            if (! Schema::hasColumn('properties', 'seller_name')) {
                $table->string('seller_name')->nullable();
            }
            if (! Schema::hasColumn('properties', 'seller_contact')) {
                $table->string('seller_contact')->nullable();
            }
            if (! Schema::hasColumn('properties', 'submitted_by')) {
                $table->foreignId('submitted_by')->nullable()->after('represented_by')->constrained('users')->nullOnDelete();
                $table->index(['submitted_by', 'status']);
            }
        });

        if (! Schema::hasTable('property_media')) {
        Schema::create('property_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind');
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('property_information_requests')) {
        Schema::create('property_information_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->text('question');
            $table->text('response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['property_id', 'responded_at']);
        });
        }

        Schema::table('broker_introductions', function (Blueprint $table) {
            if (! Schema::hasColumn('broker_introductions', 'buyer_name')) {
                $table->unsignedBigInteger('buyer_id')->nullable()->change();
                $table->string('buyer_name')->nullable();
                $table->string('buyer_email')->nullable()->index();
                $table->string('buyer_phone')->nullable();
                $table->text('consent_note')->nullable();
                $table->string('consent_path')->nullable();
                $table->string('evidence_path')->nullable();
                $table->string('source')->nullable();
                $table->text('commission_terms')->nullable();
                $table->text('notes')->nullable();
                $table->json('conflicts')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('broker_introduction_property')) {
            Schema::create('broker_introduction_property', function (Blueprint $table) {
                $table->id();
                $table->foreignId('broker_introduction_id')->constrained()->cascadeOnDelete();
                $table->foreignId('property_id')->constrained()->cascadeOnDelete();
                $table->unique(['broker_introduction_id', 'property_id'], 'broker_intro_property_unique');
            });
        } elseif (Schema::getConnection()->select("SHOW INDEX FROM broker_introduction_property WHERE Key_name = 'broker_intro_property_unique'") === []) {
            Schema::table('broker_introduction_property', function (Blueprint $table) {
                $table->unique(['broker_introduction_id', 'property_id'], 'broker_intro_property_unique');
            });
        }

        if (! Schema::hasTable('broker_introduction_audits')) {
        Schema::create('broker_introduction_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('broker_introduction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['broker_introduction_id', 'created_at'], 'broker_intro_audits_created_idx');
        });
        } elseif (Schema::getConnection()->select("SHOW INDEX FROM broker_introduction_audits WHERE Key_name = 'broker_intro_audits_created_idx'") === []) {
            Schema::table('broker_introduction_audits', function (Blueprint $table) {
                $table->index(['broker_introduction_id', 'created_at'], 'broker_intro_audits_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('broker_introduction_audits');
        Schema::dropIfExists('broker_introduction_property');

        Schema::table('broker_introductions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'buyer_name',
                'buyer_email',
                'buyer_phone',
                'consent_note',
                'consent_path',
                'evidence_path',
                'source',
                'commission_terms',
                'notes',
                'conflicts',
                'submitted_at',
                'reviewed_at',
            ]);
        });

        Schema::dropIfExists('property_information_requests');
        Schema::dropIfExists('property_media');

        Schema::table('properties', function (Blueprint $table) {
            $table->dropIndex(['submitted_by', 'status']);
            $table->dropConstrainedForeignId('submitted_by');
            $table->dropColumn(['parcel_identifier', 'seller_name', 'seller_contact']);
        });
    }
};
