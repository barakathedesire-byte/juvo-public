<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'national_id')) {
                $table->string('national_id')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('customers', 'next_of_kin_name')) {
                $table->string('next_of_kin_name')->nullable()->after('address');
            }
            if (! Schema::hasColumn('customers', 'next_of_kin_phone')) {
                $table->string('next_of_kin_phone')->nullable()->after('next_of_kin_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['national_id', 'next_of_kin_name', 'next_of_kin_phone']);
        });
    }
};
