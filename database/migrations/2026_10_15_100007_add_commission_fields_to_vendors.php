<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'commission_rate')) {
                $table->decimal('commission_rate', 6, 4)->nullable()->after('status');
            }
            if (! Schema::hasColumn('vendors', 'minimum_payout')) {
                $table->decimal('minimum_payout', 12, 2)->nullable()->after('commission_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            foreach (['commission_rate', 'minimum_payout'] as $column) {
                if (Schema::hasColumn('vendors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
