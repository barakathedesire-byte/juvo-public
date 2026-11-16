<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_attributions', function (Blueprint $table) {
            $table->timestamp('attributed_at')->nullable()->after('method');
            $table->string('source')->default('agent')->after('method');
            $table->foreignId('policy_id')->nullable()->after('commission_rate')->constrained('agent_commission_policies')->nullOnDelete();
            $table->unsignedInteger('policy_version')->nullable()->after('policy_id');
            $table->string('commission_basis')->nullable()->after('policy_version');

            $table->boolean('agent_confirmed')->default(false)->after('status');
            $table->string('confirmation_method')->nullable()->after('agent_confirmed');
            $table->timestamp('confirmed_at')->nullable()->after('confirmation_method');
            $table->timestamp('confirmation_deadline')->nullable()->after('confirmed_at');
            $table->string('dispute_status')->default('none')->after('confirmation_deadline');

            $table->index('source');
            $table->index('dispute_status');
        });
    }

    public function down(): void
    {
        Schema::table('sales_attributions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('policy_id');
            $table->dropColumn([
                'attributed_at', 'source', 'policy_version', 'commission_basis',
                'agent_confirmed', 'confirmation_method', 'confirmed_at',
                'confirmation_deadline', 'dispute_status',
            ]);
        });
    }
};
