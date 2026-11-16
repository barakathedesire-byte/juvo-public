<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pricing & Promotion Optimisation Engine (Algorithm 03).
 *
 * Extends the existing promotions table into a lifecycle-aware, funding-aware
 * rule, and adds the supporting tables the pricing engine needs: atomic budget
 * reservations, per-order redemption records, and an auditable price history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            if (! Schema::hasColumn('promotions', 'status')) {
                $table->string('status', 20)->default('active')->after('active');
            }
            if (! Schema::hasColumn('promotions', 'scope')) {
                $table->string('scope', 20)->default('product')->after('status');
            }
            if (! Schema::hasColumn('promotions', 'category_id')) {
                $table->unsignedBigInteger('category_id')->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('promotions', 'funding_source')) {
                $table->string('funding_source', 20)->default('vendor')->after('value');
            }
            if (! Schema::hasColumn('promotions', 'stack_group')) {
                $table->string('stack_group', 40)->nullable()->after('funding_source');
            }
            if (! Schema::hasColumn('promotions', 'priority')) {
                $table->integer('priority')->default(0)->after('stack_group');
            }
            if (! Schema::hasColumn('promotions', 'budget_amount')) {
                $table->decimal('budget_amount', 14, 2)->nullable()->after('priority');
            }
            if (! Schema::hasColumn('promotions', 'budget_spent')) {
                $table->decimal('budget_spent', 14, 2)->default(0)->after('budget_amount');
            }
            if (! Schema::hasColumn('promotions', 'per_order_cap')) {
                $table->decimal('per_order_cap', 14, 2)->nullable()->after('budget_spent');
            }
            if (! Schema::hasColumn('promotions', 'coupon_compatible')) {
                $table->boolean('coupon_compatible')->default(false)->after('per_order_cap');
            }
            if (! Schema::hasColumn('promotions', 'price_floor')) {
                $table->decimal('price_floor', 14, 2)->nullable()->after('coupon_compatible');
            }
            if (! Schema::hasColumn('promotions', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('price_floor');
            }
            if (! Schema::hasColumn('promotions', 'approved_by')) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('created_by');
            }
            if (! Schema::hasColumn('promotions', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }
            if (! Schema::hasColumn('promotions', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('promotions', 'meta')) {
                $table->json('meta')->nullable()->after('rejection_reason');
            }
        });

        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'funding_source')) {
                $table->string('funding_source', 20)->nullable()->after('vendor_id');
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'pricing_breakdown')) {
                $table->json('pricing_breakdown')->nullable()->after('total_amount');
            }
            if (! Schema::hasColumn('orders', 'juvo_funded_discount')) {
                $table->decimal('juvo_funded_discount', 14, 2)->default(0)->after('discount_amount');
            }
            if (! Schema::hasColumn('orders', 'vendor_funded_discount')) {
                $table->decimal('vendor_funded_discount', 14, 2)->default(0)->after('juvo_funded_discount');
            }
        });

        Schema::table('order_details', function (Blueprint $table) {
            if (! Schema::hasColumn('order_details', 'discount_amount')) {
                $table->decimal('discount_amount', 14, 2)->default(0)->after('price');
            }
            if (! Schema::hasColumn('order_details', 'funding_breakdown')) {
                $table->json('funding_breakdown')->nullable()->after('discount_amount');
            }
        });

        if (! Schema::hasTable('promotion_reservations')) {
            Schema::create('promotion_reservations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('promotion_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->string('reference', 120)->unique();
                $table->decimal('amount', 14, 2);
                $table->string('status', 20)->default('held');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['promotion_id', 'status']);
                $table->foreign('promotion_id')->references('id')->on('promotions')->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('promotion_redemptions')) {
            Schema::create('promotion_redemptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('promotion_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->unsignedBigInteger('order_detail_id')->nullable();
                $table->decimal('discount_amount', 14, 2);
                $table->string('funding_source', 20)->default('vendor');
                $table->unsignedBigInteger('funded_vendor_id')->nullable();
                $table->string('reference', 120)->nullable();
                $table->timestamps();

                $table->unique(['order_id', 'promotion_id', 'order_detail_id'], 'promo_redeem_unique');
                $table->index(['promotion_id', 'funding_source']);
            });
        }

        if (! Schema::hasTable('price_change_logs')) {
            Schema::create('price_change_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('variant_id')->nullable();
                $table->unsignedBigInteger('vendor_id')->nullable();
                $table->string('field', 40);
                $table->decimal('old_value', 14, 2)->nullable();
                $table->decimal('new_value', 14, 2)->nullable();
                $table->string('source', 40)->default('manual');
                $table->string('actor_type', 40)->nullable();
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->string('reason', 255)->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['product_id', 'created_at']);
                $table->index(['variant_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('price_change_logs');
        Schema::dropIfExists('promotion_redemptions');
        Schema::dropIfExists('promotion_reservations');

        Schema::table('order_details', function (Blueprint $table) {
            foreach (['discount_amount', 'funding_breakdown'] as $column) {
                if (Schema::hasColumn('order_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            foreach (['pricing_breakdown', 'juvo_funded_discount', 'vendor_funded_discount'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'funding_source')) {
                $table->dropColumn('funding_source');
            }
        });

        Schema::table('promotions', function (Blueprint $table) {
            foreach ([
                'status', 'scope', 'category_id', 'funding_source', 'stack_group', 'priority',
                'budget_amount', 'budget_spent', 'per_order_cap', 'coupon_compatible', 'price_floor',
                'created_by', 'approved_by', 'approved_at', 'rejection_reason', 'meta',
            ] as $column) {
                if (Schema::hasColumn('promotions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
