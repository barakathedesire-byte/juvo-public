<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['shipping_policy', 'return_policy', 'warranty_policy']);
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->dropColumn(['return_policy', 'shipping_policy', 'privacy_policy']);
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->text('shipping_policy')->nullable();
            $table->text('return_policy')->nullable();
            $table->text('warranty_policy')->nullable();
        });

        Schema::table('vendors', function (Blueprint $table) {
            $table->text('return_policy')->nullable();
            $table->text('shipping_policy')->nullable();
            $table->text('privacy_policy')->nullable();
        });
    }
};
