<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('role')
                    ->constrained('staff_departments')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'position')) {
                $table->string('position')->nullable()->after('department_id');
            }
            if (! Schema::hasColumn('users', 'account_status')) {
                $table->enum('account_status', ['active', 'suspended'])->default('active')->after('position');
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('account_status');
            }
            if (! Schema::hasColumn('users', 'access_scope')) {
                $table->json('access_scope')->nullable()->after('last_login_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            foreach (['department_id', 'position', 'account_status', 'last_login_at', 'access_scope'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    if ($column === 'department_id') {
                        $table->dropForeign(['department_id']);
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};
