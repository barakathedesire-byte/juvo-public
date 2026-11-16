<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'sales_rep_id')) {
                $table->foreignId('sales_rep_id')->nullable()->after('id')->constrained('sales_reps')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'region_id')) {
                $table->foreignId('region_id')->nullable()->after('address')->constrained('regions')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'location_id')) {
                $table->foreignId('location_id')->nullable()->after('region_id')->constrained('locations')->nullOnDelete();
            }
            if (! Schema::hasColumn('customers', 'delivery_address')) {
                $table->text('delivery_address')->nullable()->after('location_id');
            }
            if (! Schema::hasColumn('customers', 'crm_status')) {
                $table->string('crm_status', 40)->default('new')->after('status');
            }
            if (! Schema::hasColumn('customers', 'last_contacted_at')) {
                $table->timestamp('last_contacted_at')->nullable()->after('updated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            foreach (['sales_rep_id', 'region_id', 'location_id'] as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropForeign([$column]);
                }
            }
            $table->dropColumn(array_filter([
                'sales_rep_id', 'region_id', 'location_id', 'delivery_address', 'crm_status', 'last_contacted_at',
            ], fn ($column) => Schema::hasColumn('customers', $column)));
        });
    }
};
