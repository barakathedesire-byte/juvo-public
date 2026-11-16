<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen the commission lifecycle: sales start `pending` (order in
        // progress), become `available` once the order completes, then
        // `settled` when paid out, or `reversed` on cancel/refund.
        DB::statement("ALTER TABLE vendor_commissions MODIFY COLUMN status ENUM('pending','available','settled','reversed') NOT NULL DEFAULT 'pending'");

        Schema::table('vendor_commissions', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_commissions', 'payout_id')) {
                $table->foreignId('payout_id')->nullable()->after('status')
                    ->constrained('vendor_payouts')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_commissions', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_commissions', 'payout_id')) {
                $table->dropConstrainedForeignId('payout_id');
            }
        });

        DB::statement("ALTER TABLE vendor_commissions MODIFY COLUMN status ENUM('pending','settled','reversed') NOT NULL DEFAULT 'pending'");
    }
};
