<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('currencies')->update(['code' => DB::raw('UPPER(code)')]);
    }

    public function down(): void
    {
        //
    }
};
