<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `logistics_admins` MODIFY `is_active` TINYINT(1) NOT NULL DEFAULT 1"
        );

        Schema::table('logistics_admins', function (Blueprint $table) {
            if (! Schema::hasColumn('logistics_admins', 'account_status')) {
                $table->string('account_status', 32)->default('active')->after('is_active');
            }
            if (! Schema::hasColumn('logistics_admins', 'portal_access')) {
                $table->boolean('portal_access')->default(true)->after('account_status');
            }
            if (! Schema::hasColumn('logistics_admins', 'portal_approved_at')) {
                $table->timestamp('portal_approved_at')->nullable()->after('portal_access');
            }
            if (! Schema::hasColumn('logistics_admins', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('portal_approved_at');
            }
            if (! Schema::hasColumn('logistics_admins', 'auth_version')) {
                $table->unsignedInteger('auth_version')->default(0)->after('locked_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('logistics_admins', function (Blueprint $table) {
            foreach (['auth_version', 'locked_until', 'portal_approved_at', 'portal_access', 'account_status'] as $column) {
                if (Schema::hasColumn('logistics_admins', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
