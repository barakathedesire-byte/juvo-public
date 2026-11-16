<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'customer_id')) {
                $table->foreignId('customer_id')->nullable()->after('user_id')
                    ->constrained('customers')->nullOnDelete();
            }
            if (! Schema::hasColumn('payments', 'payment_method')) {
                $table->string('payment_method', 50)->nullable()->after('currency');
            }
        });

        // Customers live in their own table (not `users`), and cash-on-delivery
        // orders have no gateway. doctrine/dbal is not installed, so relax the
        // NOT NULL constraints with a raw statement.
        DB::statement('ALTER TABLE payments MODIFY user_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE payments MODIFY gateway_id BIGINT UNSIGNED NULL');

        Schema::table('shipping_addresses', function (Blueprint $table) {
            if (! Schema::hasColumn('shipping_addresses', 'state')) {
                $table->string('state', 120)->nullable()->after('city');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shipping_addresses', function (Blueprint $table) {
            if (Schema::hasColumn('shipping_addresses', 'state')) {
                $table->dropColumn('state');
            }
        });

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'payment_method')) {
                $table->dropColumn('payment_method');
            }
            if (Schema::hasColumn('payments', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
        });
    }
};
