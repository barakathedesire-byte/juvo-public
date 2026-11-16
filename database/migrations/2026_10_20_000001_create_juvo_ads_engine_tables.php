<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Juvo Ads engine: advertising accounts, append-only ledger, budget
 * reservations, auction decisions, targeting, attribution, optimisation runs,
 * experiments, policy reviews and an advertising audit log.
 *
 * Money is stored in integer minor units with an explicit currency. The legacy
 * decimal columns on ad_campaigns stay for backward compatibility and are kept
 * in sync by the money service.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vendor_id');
            $table->char('currency', 3)->default('TZS');
            $table->string('status')->default('active'); // active | restricted | suspended
            $table->unsignedBigInteger('credit_limit_minor')->default(0);
            $table->unsignedBigInteger('funded_minor')->default(0);
            $table->unsignedBigInteger('spent_minor')->default(0);
            $table->unsignedBigInteger('reserved_minor')->default(0);
            $table->boolean('auto_bid_enabled')->default(false);
            $table->string('auto_bid_strategy')->nullable(); // max_clicks | target_cpa | target_roas
            $table->unsignedBigInteger('max_cpc_minor')->nullable();
            $table->unsignedBigInteger('target_cpa_minor')->nullable();
            $table->decimal('target_roas', 8, 2)->nullable();
            $table->unsignedBigInteger('daily_spend_cap_minor')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->unique('vendor_id');
        });

        Schema::create('ad_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('vendor_id');
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->string('type'); // funding | spend | reservation | release | refund | adjustment | bonus
            $table->string('direction'); // credit | debit
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->bigInteger('balance_after_minor');
            $table->bigInteger('reserved_after_minor');
            $table->string('reference')->nullable();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('account_id')->references('id')->on('ad_accounts')->cascadeOnDelete();
            $table->foreign('vendor_id')->references('id')->on('vendors')->cascadeOnDelete();
            $table->foreign('campaign_id')->references('id')->on('ad_campaigns')->nullOnDelete();
            $table->unique('reference');
            $table->index(['account_id', 'type']);
            $table->index(['campaign_id', 'type']);
        });

        Schema::create('ad_budget_reservations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('account_id');
            $table->string('period'); // day | lifetime
            $table->string('period_key'); // YYYY-MM-DD or "lifetime"
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status')->default('held'); // held | committed | released
            $table->string('reference')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('ad_campaigns')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('ad_accounts')->cascadeOnDelete();
            $table->unique('reference');
            $table->index(['campaign_id', 'period', 'period_key']);
        });

        Schema::create('ad_auction_decisions', function (Blueprint $table) {
            $table->id();
            $table->string('request_uid');
            $table->string('placement_key');
            $table->unsignedBigInteger('placement_id')->nullable();
            $table->string('context_hash');
            $table->string('algorithm_version');
            $table->unsignedInteger('candidate_count')->default(0);
            $table->unsignedBigInteger('winner_campaign_id')->nullable();
            $table->json('winners')->nullable();
            $table->json('candidates')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique('request_uid');
        });

        Schema::create('ad_targeting_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->string('kind'); // include | exclude
            $table->string('dimension'); // keyword | category | brand | product | vendor | region | device
            $table->string('operator')->default('in'); // in | not_in | equals | contains
            $table->string('value');
            $table->timestamp('created_at')->nullable();

            $table->foreign('campaign_id')->references('id')->on('ad_campaigns')->cascadeOnDelete();
            $table->index(['campaign_id', 'dimension']);
        });

        Schema::create('ad_attribution_touchpoints', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_detail_id')->nullable();
            $table->string('event_type'); // impression | click | conversion
            $table->string('model')->default('last_click');
            $table->string('click_id')->nullable();
            $table->unsignedBigInteger('revenue_minor')->default(0);
            $table->char('currency', 3);
            $table->string('status')->default('pending'); // pending | attributed | expired | rejected
            $table->string('reference')->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('ad_campaigns')->cascadeOnDelete();
            $table->unique(['order_id', 'campaign_id', 'model'], 'ad_attr_order_campaign_model_unique');
            $table->index(['campaign_id', 'event_type']);
        });

        Schema::create('ad_optimization_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->string('algorithm_version');
            $table->string('strategy');
            $table->unsignedBigInteger('before_bid_minor');
            $table->unsignedBigInteger('after_bid_minor');
            $table->json('metrics')->nullable();
            $table->string('status')->default('applied'); // applied | skipped | failed
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('campaign_id')->references('id')->on('ad_campaigns')->cascadeOnDelete();
        });

        Schema::create('ad_experiments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->string('key');
            $table->string('variant');
            $table->unsignedInteger('allocation')->default(50);
            $table->string('status')->default('running'); // running | stopped
            $table->json('metrics')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('ad_campaigns')->cascadeOnDelete();
            $table->unique(['campaign_id', 'key', 'variant']);
        });

        Schema::create('ad_policy_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('creative_id')->nullable();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->string('decision'); // approved | rejected | changes_requested
            $table->string('reason_code')->nullable();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('campaign_id')->references('id')->on('ad_campaigns')->cascadeOnDelete();
            $table->index(['campaign_id', 'decision']);
        });

        Schema::create('ad_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->string('action');
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->string('ip')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('action');
            $table->index('campaign_id');
        });

        $this->extendTables();
    }

    protected function extendTables(): void
    {
        Schema::table('ad_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_campaigns', 'currency')) {
                $table->char('currency', 3)->default('TZS')->after('billing_model');
            }
            if (! Schema::hasColumn('ad_campaigns', 'bid_type')) {
                $table->string('bid_type')->default('manual_cpc')->after('currency');
            }
            if (! Schema::hasColumn('ad_campaigns', 'bid_minor')) {
                $table->unsignedBigInteger('bid_minor')->default(0)->after('bid_type');
            }
            if (! Schema::hasColumn('ad_campaigns', 'max_cpc_minor')) {
                $table->unsignedBigInteger('max_cpc_minor')->nullable()->after('bid_minor');
            }
            if (! Schema::hasColumn('ad_campaigns', 'quality_factor')) {
                $table->decimal('quality_factor', 6, 4)->default(1.0000)->after('max_cpc_minor');
            }
            if (! Schema::hasColumn('ad_campaigns', 'total_budget_minor')) {
                $table->unsignedBigInteger('total_budget_minor')->default(0)->after('quality_factor');
            }
            if (! Schema::hasColumn('ad_campaigns', 'daily_budget_minor')) {
                $table->unsignedBigInteger('daily_budget_minor')->nullable()->after('total_budget_minor');
            }
            if (! Schema::hasColumn('ad_campaigns', 'amount_paid_minor')) {
                $table->unsignedBigInteger('amount_paid_minor')->default(0)->after('daily_budget_minor');
            }
            if (! Schema::hasColumn('ad_campaigns', 'spent_minor')) {
                $table->unsignedBigInteger('spent_minor')->default(0)->after('amount_paid_minor');
            }
            if (! Schema::hasColumn('ad_campaigns', 'reserved_minor')) {
                $table->unsignedBigInteger('reserved_minor')->default(0)->after('spent_minor');
            }
            if (! Schema::hasColumn('ad_campaigns', 'target_cpa_minor')) {
                $table->unsignedBigInteger('target_cpa_minor')->nullable()->after('reserved_minor');
            }
            if (! Schema::hasColumn('ad_campaigns', 'target_roas')) {
                $table->decimal('target_roas', 8, 2)->nullable()->after('target_cpa_minor');
            }
            if (! Schema::hasColumn('ad_campaigns', 'auto_bid_enabled')) {
                $table->boolean('auto_bid_enabled')->default(false)->after('target_roas');
            }
            if (! Schema::hasColumn('ad_campaigns', 'pacing')) {
                $table->string('pacing')->default('even')->after('auto_bid_enabled');
            }
            if (! Schema::hasColumn('ad_campaigns', 'attribution_window_days')) {
                $table->unsignedInteger('attribution_window_days')->default(7)->after('pacing');
            }
        });

        Schema::table('ad_placements', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_placements', 'format')) {
                $table->string('format')->default('card')->after('pricing_model');
            }
            if (! Schema::hasColumn('ad_placements', 'min_bid_minor')) {
                $table->unsignedBigInteger('min_bid_minor')->default(0)->after('format');
            }
            if (! Schema::hasColumn('ad_placements', 'settings')) {
                $table->json('settings')->nullable()->after('min_bid_minor');
            }
        });

        Schema::table('ad_events', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_events', 'event_uid')) {
                $table->string('event_uid')->nullable()->after('type');
            }
            if (! Schema::hasColumn('ad_events', 'decision_id')) {
                $table->unsignedBigInteger('decision_id')->nullable()->after('event_uid');
            }
            if (! Schema::hasColumn('ad_events', 'spend_minor')) {
                $table->unsignedBigInteger('spend_minor')->default(0)->after('decision_id');
            }
            if (! Schema::hasColumn('ad_events', 'currency')) {
                $table->char('currency', 3)->nullable()->after('spend_minor');
            }
            if (! Schema::hasColumn('ad_events', 'is_billable')) {
                $table->boolean('is_billable')->default(false)->after('currency');
            }
            if (! Schema::hasColumn('ad_events', 'is_valid')) {
                $table->boolean('is_valid')->default(true)->after('is_billable');
            }
            if (! Schema::hasColumn('ad_events', 'invalid_reason')) {
                $table->string('invalid_reason')->nullable()->after('is_valid');
            }
        });

        Schema::table('ad_events', function (Blueprint $table) {
            $table->unique('event_uid');
        });

        Schema::table('ad_daily_stats', function (Blueprint $table) {
            if (! Schema::hasColumn('ad_daily_stats', 'spend_minor')) {
                $table->unsignedBigInteger('spend_minor')->default(0)->after('spend');
            }
            if (! Schema::hasColumn('ad_daily_stats', 'currency')) {
                $table->char('currency', 3)->nullable()->after('spend_minor');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ad_events', function (Blueprint $table) {
            $table->dropUnique(['event_uid']);
        });

        Schema::table('ad_daily_stats', function (Blueprint $table) {
            foreach (['spend_minor', 'currency'] as $col) {
                if (Schema::hasColumn('ad_daily_stats', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('ad_events', function (Blueprint $table) {
            foreach (['event_uid', 'decision_id', 'spend_minor', 'currency', 'is_billable', 'is_valid', 'invalid_reason'] as $col) {
                if (Schema::hasColumn('ad_events', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('ad_placements', function (Blueprint $table) {
            foreach (['format', 'min_bid_minor', 'settings'] as $col) {
                if (Schema::hasColumn('ad_placements', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('ad_campaigns', function (Blueprint $table) {
            foreach ([
                'currency', 'bid_type', 'bid_minor', 'max_cpc_minor', 'quality_factor',
                'total_budget_minor', 'daily_budget_minor', 'amount_paid_minor', 'spent_minor',
                'reserved_minor', 'target_cpa_minor', 'target_roas', 'auto_bid_enabled',
                'pacing', 'attribution_window_days',
            ] as $col) {
                if (Schema::hasColumn('ad_campaigns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('ad_audit_logs');
        Schema::dropIfExists('ad_policy_reviews');
        Schema::dropIfExists('ad_experiments');
        Schema::dropIfExists('ad_optimization_runs');
        Schema::dropIfExists('ad_attribution_touchpoints');
        Schema::dropIfExists('ad_targeting_rules');
        Schema::dropIfExists('ad_auction_decisions');
        Schema::dropIfExists('ad_budget_reservations');
        Schema::dropIfExists('ad_ledger_entries');
        Schema::dropIfExists('ad_accounts');
    }
};
