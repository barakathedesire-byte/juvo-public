<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->foreignId('rider_id')->constrained('riders')->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('status', 32)->default('pending_approval');
            $table->string('method', 32)->default('bank');
            $table->string('account_name')->nullable();
            $table->text('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('provider_reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('initiated_by_type')->nullable();
            $table->unsignedBigInteger('initiated_by_id')->nullable();
            $table->string('initiated_by_name')->nullable();
            $table->string('approved_by_type')->nullable();
            $table->unsignedBigInteger('approved_by_id')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('completed_by_type')->nullable();
            $table->unsignedBigInteger('completed_by_id')->nullable();
            $table->string('completed_by_name')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('failed_by_type')->nullable();
            $table->unsignedBigInteger('failed_by_id')->nullable();
            $table->string('failed_by_name')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('reversed_by_type')->nullable();
            $table->unsignedBigInteger('reversed_by_id')->nullable();
            $table->string('reversed_by_name')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('provider_reference');
        });

        Schema::create('rider_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 64)->unique();
            $table->foreignId('rider_id')->constrained('riders')->restrictOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('rider_payout_id')->nullable()->unique()->constrained('rider_payouts')->nullOnDelete();
            $table->string('type', 32);
            $table->string('direction', 16);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('TZS');
            $table->string('status', 32)->default('available');
            $table->string('description')->nullable();
            $table->string('source_key', 120)->nullable()->unique();
            $table->json('meta')->nullable();
            $table->string('created_by_type')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->string('approved_by_type')->nullable();
            $table->unsignedBigInteger('approved_by_id')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['rider_id', 'status']);
            $table->index(['rider_id', 'direction', 'status']);
            $table->index(['type', 'status']);
            $table->index('occurred_at');
        });

        DB::table('orders')
            ->whereNotNull('assigned_driver_id')
            ->where('logistics_status', 'delivered')
            ->where('delivery_fee', '>', 0)
            ->select(['id', 'assigned_driver_id', 'currency', 'delivery_fee', 'delivered_at'])
            ->orderBy('id')
            ->chunkById(200, function ($orders) {
                $now = now();
                $rows = [];

                foreach ($orders as $order) {
                    $rows[] = [
                        'reference' => 'ERN-LEGACY-'.$order->id,
                        'rider_id' => $order->assigned_driver_id,
                        'order_id' => $order->id,
                        'rider_payout_id' => null,
                        'type' => 'delivery_earning',
                        'direction' => 'credit',
                        'amount' => $order->delivery_fee,
                        'currency' => strtoupper($order->currency ?: 'TZS'),
                        'status' => 'available',
                        'description' => 'Delivery earnings for order #'.$order->id,
                        'source_key' => 'order:'.$order->id.':delivery_earning',
                        'meta' => null,
                        'created_by_type' => null,
                        'created_by_id' => null,
                        'created_by_name' => 'System backfill',
                        'approved_by_type' => null,
                        'approved_by_id' => null,
                        'approved_by_name' => null,
                        'approved_at' => null,
                        'occurred_at' => $order->delivered_at ?: $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('rider_ledger_entries')->insertOrIgnore($rows);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_ledger_entries');
        Schema::dropIfExists('rider_payouts');
    }
};
