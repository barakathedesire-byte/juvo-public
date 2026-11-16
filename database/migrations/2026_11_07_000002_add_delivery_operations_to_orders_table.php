<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'assigned_at')) {
                $table->timestamp('assigned_at')->nullable()->after('driver_phone');
            }
            if (! Schema::hasColumn('orders', 'accepted_at')) {
                $table->timestamp('accepted_at')->nullable()->after('assigned_at');
            }
            if (! Schema::hasColumn('orders', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('pod_delivered_at');
            }
            if (! Schema::hasColumn('orders', 'failed_reason')) {
                $table->string('failed_reason', 64)->nullable()->after('failed_at');
            }
            if (! Schema::hasColumn('orders', 'failure_note')) {
                $table->text('failure_note')->nullable()->after('failed_reason');
            }
            if (! Schema::hasColumn('orders', 'delivery_attempts')) {
                $table->unsignedTinyInteger('delivery_attempts')->default(0)->after('failure_note');
            }
            if (! Schema::hasColumn('orders', 'scheduled_for')) {
                $table->date('scheduled_for')->nullable()->after('delivery_attempts');
            }
            if (! Schema::hasColumn('orders', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('scheduled_for');
            }
            if (! Schema::hasColumn('orders', 'pod_receiver_name')) {
                $table->string('pod_receiver_name')->nullable()->after('pod_signature_url');
            }
            if (! Schema::hasColumn('orders', 'pod_notes')) {
                $table->text('pod_notes')->nullable()->after('pod_receiver_name');
            }
            if (! Schema::hasColumn('orders', 'cod_amount')) {
                $table->decimal('cod_amount', 12, 2)->nullable()->after('pod_notes');
            }
            if (! Schema::hasColumn('orders', 'cod_collected_at')) {
                $table->timestamp('cod_collected_at')->nullable()->after('cod_amount');
            }
            if (! Schema::hasColumn('orders', 'cod_remitted_at')) {
                $table->timestamp('cod_remitted_at')->nullable()->after('cod_collected_at');
            }
            if (! Schema::hasColumn('orders', 'cod_remittance_ref')) {
                $table->string('cod_remittance_ref', 100)->nullable()->after('cod_remitted_at');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index('assigned_driver_id');
            $table->index('failed_at');
            $table->index('cod_collected_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['assigned_driver_id']);
            $table->dropIndex(['failed_at']);
            $table->dropIndex(['cod_collected_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'assigned_at',
                'accepted_at',
                'failed_at',
                'failed_reason',
                'failure_note',
                'delivery_attempts',
                'scheduled_for',
                'returned_at',
                'pod_receiver_name',
                'pod_notes',
                'cod_amount',
                'cod_collected_at',
                'cod_remitted_at',
                'cod_remittance_ref',
            ]);
        });
    }
};
