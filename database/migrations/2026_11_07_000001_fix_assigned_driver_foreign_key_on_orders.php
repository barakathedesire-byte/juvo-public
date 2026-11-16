<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['assigned_driver_id']);
            });
        }

        if (Schema::hasTable('riders')) {
            DB::table('orders')
                ->whereNotNull('assigned_driver_id')
                ->whereNotIn('assigned_driver_id', DB::table('riders')->select('id'))
                ->update(['assigned_driver_id' => null]);

            if ($driver !== 'sqlite') {
                Schema::table('orders', function (Blueprint $table) {
                    $table->foreign('assigned_driver_id')
                        ->references('id')
                        ->on('riders')
                        ->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['assigned_driver_id']);
            });
        }

        if ($driver !== 'sqlite') {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('assigned_driver_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }
};
