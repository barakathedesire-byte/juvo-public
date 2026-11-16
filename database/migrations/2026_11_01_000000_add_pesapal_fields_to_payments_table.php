<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            // Pesapal specific fields
            $table->string('merchant_reference', 100)->nullable()->after('order_id');
            $table->string('pesapal_transaction_id', 100)->nullable()->after('merchant_reference');
            $table->string('ipn_id', 100)->nullable()->after('pesapal_transaction_id');
            $table->index('merchant_reference');
            $table->index('pesapal_transaction_id');
            $table->index('ipn_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['merchant_reference']);
            $table->dropIndex(['pesapal_transaction_id']);
            $table->dropIndex(['ipn_id']);
            $table->dropColumn([
                'merchant_reference',
                'pesapal_transaction_id',
                'ipn_id',
            ]);
        });
    }
};
