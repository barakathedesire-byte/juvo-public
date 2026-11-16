<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Full staff account lifecycle. `active` is the only state that may enter
     * the admin portal; the rest are denied with a clear, non-sensitive
     * message. Portal access is tracked separately from the account state so an
     * identity can exist (invited) before it is approved for the portal.
     */
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE `users` MODIFY `account_status` '
            ."ENUM('invitation_pending','awaiting_approval','active','locked','disabled','suspended','revoked') "
            ."NOT NULL DEFAULT 'active'"
        );

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'portal_access')) {
                $table->boolean('portal_access')->default(true)->after('account_status');
            }
            if (! Schema::hasColumn('users', 'portal_approved_at')) {
                $table->timestamp('portal_approved_at')->nullable()->after('portal_access');
            }
            if (! Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable()->after('portal_approved_at');
            }
            if (! Schema::hasColumn('users', 'auth_version')) {
                $table->unsignedInteger('auth_version')->default(0)->after('locked_until');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['auth_version', 'locked_until', 'portal_approved_at', 'portal_access'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasColumn('users', 'account_status')) {
            DB::statement("ALTER TABLE `users` MODIFY `account_status` ENUM('active','suspended') NOT NULL DEFAULT 'active'");
        }
    }
};
