<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            if (! Schema::hasColumn('collections', 'image')) {
                $table->string('image')->nullable()->after('name');
            }

            if (! Schema::hasColumn('collections', 'bg_color')) {
                $table->string('bg_color', 20)->nullable()->after('image');
            }

            if (! Schema::hasColumn('collections', 'text_color')) {
                $table->string('text_color', 20)->nullable()->after('bg_color');
            }

            if (! Schema::hasColumn('collections', 'size')) {
                $table->string('size', 20)->default('md')->after('text_color');
            }

            if (! Schema::hasColumn('collections', 'shape')) {
                $table->string('shape', 20)->default('rounded')->after('size');
            }
        });
    }

    public function down(): void
    {
        Schema::table('collections', function (Blueprint $table) {
            foreach (['shape', 'size', 'text_color', 'bg_color', 'image'] as $column) {
                if (Schema::hasColumn('collections', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
