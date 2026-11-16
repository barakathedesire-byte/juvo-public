<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_commission_policies', function (Blueprint $table) {
            $table->id();
            $table->string('policy_key')->nullable();
            $table->string('name');
            $table->text('description')->nullable();

            $table->decimal('rate', 6, 4)->default(0);
            $table->decimal('fixed_amount', 12, 2)->default(0);
            $table->string('basis')->default('merch_after_discount_ex_tax');

            $table->decimal('min_order_value', 12, 2)->nullable();
            $table->decimal('max_commission', 12, 2)->nullable();

            $table->json('eligible_category_ids')->nullable();
            $table->json('excluded_category_ids')->nullable();

            $table->boolean('include_tax')->default(false);
            $table->boolean('include_delivery_fee')->default(false);

            $table->boolean('allow_self_purchase')->default(false);
            $table->boolean('allow_guest_orders')->default(true);

            $table->boolean('team_enabled')->default(false);
            $table->decimal('team_rate', 6, 4)->default(0);
            $table->unsignedTinyInteger('team_max_depth')->default(1);
            $table->decimal('team_max_total_rate', 6, 4)->default(0);

            $table->unsignedInteger('version')->default(1);
            $table->unsignedInteger('priority')->default(0);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'priority']);
            $table->index('is_default');
            $table->index('policy_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_commission_policies');
    }
};
