<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Extend the order lifecycle to the full fulfilment state machine.
        DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM('pending','accepted','processing','ready_for_pickup','dispatched','delivered','completed','canceled','returned') NOT NULL DEFAULT 'pending'");

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'subtotal')) {
                $table->decimal('subtotal', 12, 2)->default(0)->after('total_amount');
            }
            if (! Schema::hasColumn('orders', 'delivery_fee')) {
                $table->decimal('delivery_fee', 12, 2)->default(0)->after('subtotal');
            }
            if (! Schema::hasColumn('orders', 'shipping_method')) {
                $table->string('shipping_method')->nullable()->after('delivery_fee');
            }
            if (! Schema::hasColumn('orders', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->after('shipping_method');
            }
            if (! Schema::hasColumn('orders', 'carrier')) {
                $table->string('carrier')->nullable()->after('tracking_number');
            }
            if (! Schema::hasColumn('orders', 'payment_status')) {
                $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded', 'partially_refunded'])
                    ->default('pending')->after('carrier');
            }
            if (! Schema::hasColumn('orders', 'payment_method')) {
                $table->string('payment_method')->nullable()->after('payment_status');
            }
            if (! Schema::hasColumn('orders', 'currency')) {
                $table->string('currency', 10)->default('TZS')->after('payment_method');
            }
            if (! Schema::hasColumn('orders', 'commission_total')) {
                $table->decimal('commission_total', 12, 2)->default(0)->after('currency');
            }
            if (! Schema::hasColumn('orders', 'net_vendor_amount')) {
                $table->decimal('net_vendor_amount', 12, 2)->default(0)->after('commission_total');
            }
            if (! Schema::hasColumn('orders', 'note')) {
                $table->text('note')->nullable()->after('net_vendor_amount');
            }
            if (! Schema::hasColumn('orders', 'fulfilled_at')) {
                $table->timestamp('fulfilled_at')->nullable()->after('note');
            }
            if (! Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('fulfilled_at');
            }
            if (! Schema::hasColumn('orders', 'canceled_at')) {
                $table->timestamp('canceled_at')->nullable()->after('delivered_at');
            }
            if (! Schema::hasColumn('orders', 'cancel_reason')) {
                $table->string('cancel_reason')->nullable()->after('canceled_at');
            }
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('reason')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal', 'delivery_fee', 'shipping_method', 'tracking_number', 'carrier',
                'payment_status', 'payment_method', 'currency', 'commission_total',
                'net_vendor_amount', 'note', 'fulfilled_at', 'delivered_at', 'canceled_at',
                'cancel_reason',
            ]);
        });

        DB::statement("ALTER TABLE `orders` MODIFY `status` ENUM('pending','processing','completed','canceled') NOT NULL DEFAULT 'pending'");
    }
};
