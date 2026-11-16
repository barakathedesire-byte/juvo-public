<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            if (! Schema::hasColumn('riders', 'payout_method')) {
                $table->enum('payout_method', ['bank', 'mobile_money'])->default('bank')->after('bank_branch');
            }
            if (! Schema::hasColumn('riders', 'mobile_money_provider')) {
                $table->string('mobile_money_provider')->nullable()->after('payout_method');
            }
            if (! Schema::hasColumn('riders', 'mobile_money_number')) {
                $table->string('mobile_money_number')->nullable()->after('mobile_money_provider');
            }
            if (! Schema::hasColumn('riders', 'mobile_money_account_name')) {
                $table->string('mobile_money_account_name')->nullable()->after('mobile_money_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('riders', function (Blueprint $table) {
            foreach (['payout_method', 'mobile_money_provider', 'mobile_money_number', 'mobile_money_account_name'] as $column) {
                if (Schema::hasColumn('riders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
