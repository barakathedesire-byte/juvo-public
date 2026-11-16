<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'cod_remitted_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->decimal('cod_remitted_amount', 14, 2)->default(0)->after('cod_remittance_ref');
            });
        }

        Schema::create('cod_handovers', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->foreignId('rider_id')->constrained('riders')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('method', 32)->default('cash');
            $table->string('status', 32)->default('pending_verification');
            $table->string('external_reference', 120)->nullable();
            $table->string('evidence_path')->nullable();
            $table->text('notes')->nullable();
            $table->string('initiated_by_type')->nullable();
            $table->unsignedBigInteger('initiated_by_id')->nullable();
            $table->string('initiated_by_name')->nullable();
            $table->string('verified_by_type')->nullable();
            $table->unsignedBigInteger('verified_by_id')->nullable();
            $table->string('verified_by_name')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('rejected_by_type')->nullable();
            $table->unsignedBigInteger('rejected_by_id')->nullable();
            $table->string('rejected_by_name')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('cod_handover_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cod_handover_id')->constrained('cod_handovers')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique(['cod_handover_id', 'order_id']);
            $table->index('order_id');
        });

        Schema::create('cod_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('rider_id')->nullable()->constrained('riders')->nullOnDelete();
            $table->string('type', 32)->default('discrepancy');
            $table->string('direction', 16)->default('decrease');
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('status', 32)->default('pending_approval');
            $table->text('reason');
            $table->string('evidence_path')->nullable();
            $table->string('created_by_type')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->string('approved_by_type')->nullable();
            $table->unsignedBigInteger('approved_by_id')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('rejected_by_type')->nullable();
            $table->unsignedBigInteger('rejected_by_id')->nullable();
            $table->string('rejected_by_name')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['rider_id', 'status']);
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cod_adjustments');
        Schema::dropIfExists('cod_handover_allocations');
        Schema::dropIfExists('cod_handovers');

        if (Schema::hasColumn('orders', 'cod_remitted_amount')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('cod_remitted_amount');
            });
        }
    }
};
