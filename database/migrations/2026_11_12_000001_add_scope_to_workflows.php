<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            if (! Schema::hasColumn('workflows', 'scope')) {
                $table->string('scope', 64)->default('platform')->after('type');
                $table->index('scope');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table) {
            if (Schema::hasColumn('workflows', 'scope')) {
                $table->dropIndex(['scope']);
                $table->dropColumn('scope');
            }
        });
    }
};
