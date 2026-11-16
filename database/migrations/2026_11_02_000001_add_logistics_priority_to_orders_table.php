<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'logistics_priority')) {
                $table->string('logistics_priority', 20)->default('normal')->after('logistics_status');
                $table->index('logistics_priority');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'logistics_priority')) {
                $table->dropIndex(['logistics_priority']);
                $table->dropColumn('logistics_priority');
            }
        });
    }
};
