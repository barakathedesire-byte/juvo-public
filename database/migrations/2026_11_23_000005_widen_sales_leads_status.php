<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_leads') && Schema::hasColumn('sales_leads', 'status')) {
            DB::statement("ALTER TABLE sales_leads MODIFY status VARCHAR(40) NOT NULL DEFAULT 'new'");
        }
    }

    public function down(): void
    {
        // no-op
    }
};
