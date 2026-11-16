<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Vendor account lifecycle: new/incomplete start `inactive`, are
        // `pending` while awaiting review, `active` when approved, and may be
        // `restricted`, `suspended`, `rejected` or `banned`.
        DB::statement("ALTER TABLE vendors MODIFY COLUMN status ENUM('active','inactive','pending','restricted','suspended','rejected','banned') NOT NULL DEFAULT 'active'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vendors MODIFY COLUMN status ENUM('active','inactive','banned') NOT NULL DEFAULT 'active'");
    }
};
