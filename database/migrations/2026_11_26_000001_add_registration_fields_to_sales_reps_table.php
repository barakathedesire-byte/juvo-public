<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_reps', function (Blueprint $table) {
            if (! Schema::hasColumn('sales_reps', 'national_id')) {
                $table->string('national_id')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('sales_reps', 'address')) {
                $table->text('address')->nullable()->after('national_id');
            }
            if (! Schema::hasColumn('sales_reps', 'next_of_kin_name')) {
                $table->string('next_of_kin_name')->nullable()->after('address');
            }
            if (! Schema::hasColumn('sales_reps', 'next_of_kin_phone')) {
                $table->string('next_of_kin_phone')->nullable()->after('next_of_kin_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales_reps', function (Blueprint $table) {
            $table->dropColumn(['national_id', 'address', 'next_of_kin_name', 'next_of_kin_phone']);
        });
    }
};
