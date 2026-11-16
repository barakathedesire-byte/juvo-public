<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            if (! Schema::hasColumn('support_tickets', 'first_response_due_at')) {
                $table->dateTime('first_response_due_at')->nullable()->after('last_reply_at');
            }
            if (! Schema::hasColumn('support_tickets', 'resolution_due_at')) {
                $table->dateTime('resolution_due_at')->nullable()->after('first_response_due_at');
            }
            if (! Schema::hasColumn('support_tickets', 'first_responded_at')) {
                $table->dateTime('first_responded_at')->nullable()->after('resolution_due_at');
            }
            if (! Schema::hasColumn('support_tickets', 'resolved_at')) {
                $table->dateTime('resolved_at')->nullable()->after('first_responded_at');
            }
            if (! Schema::hasColumn('support_tickets', 'escalated_at')) {
                $table->dateTime('escalated_at')->nullable()->after('resolved_at');
            }
            if (! Schema::hasColumn('support_tickets', 'escalation_level')) {
                $table->unsignedTinyInteger('escalation_level')->default(0)->after('escalated_at');
            }
            if (! Schema::hasColumn('support_tickets', 'reopened_count')) {
                $table->unsignedTinyInteger('reopened_count')->default(0)->after('escalation_level');
            }
        });

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->index('assigned_to');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            foreach ([
                'first_response_due_at', 'resolution_due_at', 'first_responded_at',
                'resolved_at', 'escalated_at', 'escalation_level', 'reopened_count',
            ] as $column) {
                if (Schema::hasColumn('support_tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
