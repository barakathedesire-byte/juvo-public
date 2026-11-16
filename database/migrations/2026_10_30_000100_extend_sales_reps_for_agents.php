<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_reps', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->foreignId('region_id')->nullable()->after('phone')->constrained('regions')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->after('region_id')->constrained('locations')->nullOnDelete();

            $table->string('status')->default('applied')->after('is_active');
            $table->string('verification_status')->default('unverified')->after('status');
            $table->foreignId('sponsor_id')->nullable()->after('verification_status')->constrained('sales_reps')->nullOnDelete();

            $table->string('payout_method')->nullable()->after('sponsor_id');
            $table->string('payout_account_name')->nullable()->after('payout_method');
            $table->text('payout_account_number')->nullable()->after('payout_account_name');
            $table->string('payout_status')->default('incomplete')->after('payout_account_number');

            $table->string('terms_version')->nullable()->after('payout_status');
            $table->timestamp('terms_accepted_at')->nullable()->after('terms_version');

            $table->timestamp('applied_at')->nullable()->after('terms_accepted_at');
            $table->timestamp('approved_at')->nullable()->after('applied_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->timestamp('suspended_at')->nullable()->after('rejection_reason');
            $table->text('suspension_reason')->nullable()->after('suspended_at');

            $table->boolean('customer_confirmation_required')->default(true)->after('suspension_reason');
            $table->string('currency', 10)->default('TZS')->after('customer_confirmation_required');
            $table->text('notes')->nullable()->after('currency');

            $table->index('status');
            $table->index('verification_status');
            $table->index('sponsor_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales_reps', function (Blueprint $table) {
            $table->dropConstrainedForeignId('region_id');
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('sponsor_id');
            $table->dropConstrainedForeignId('approved_by');

            $table->dropColumn([
                'phone', 'status', 'verification_status', 'payout_method', 'payout_account_name',
                'payout_account_number', 'payout_status', 'terms_version', 'terms_accepted_at',
                'applied_at', 'approved_at', 'rejected_at', 'rejection_reason', 'suspended_at',
                'suspension_reason', 'customer_confirmation_required', 'currency', 'notes',
            ]);
        });
    }
};
