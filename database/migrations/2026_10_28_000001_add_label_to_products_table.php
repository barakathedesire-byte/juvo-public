<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'label')) {
                $table->string('label', 50)->nullable()->after('is_sponsored');
            }
            if (! Schema::hasColumn('products', 'label_style')) {
                $table->string('label_style', 20)->nullable()->after('label');
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'label_style')) {
                $table->dropColumn('label_style');
            }
            if (Schema::hasColumn('products', 'label')) {
                $table->dropColumn('label');
            }
        });
    }
};
