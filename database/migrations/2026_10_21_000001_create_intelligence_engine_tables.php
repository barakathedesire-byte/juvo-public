<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------------------------------------------------------------
         | Algorithm Engine — central registry of every decision algorithm.
         * ------------------------------------------------------------- */
        Schema::create('algorithms', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category', 64)->index();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index(); // draft|testing|active|disabled
            $table->boolean('is_enabled')->default(false)->index();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_implemented')->default(false);
            $table->json('config')->nullable();
            $table->json('config_schema')->nullable();
            $table->json('meta')->nullable();
            $table->string('owner')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('algorithm_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('algorithm_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('config')->nullable();
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();

            $table->unique(['algorithm_id', 'version']);
        });

        /* Recommendation event pipeline — the raw behavioural stream. */
        Schema::create('recommendation_events', function (Blueprint $table) {
            $table->id();
            $table->char('event_uuid', 36)->unique();
            $table->string('type', 32)->index(); // impression|click|add_to_cart|purchase
            $table->string('algorithm_key', 64)->nullable()->index();
            $table->unsignedInteger('algorithm_version')->nullable();
            $table->string('surface', 64)->index();
            $table->string('subject_type', 64)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();
            $table->unsignedBigInteger('order_id')->nullable()->index();
            $table->bigInteger('value_minor')->default(0);
            $table->char('currency', 3)->nullable();
            $table->unsignedInteger('position')->nullable();
            $table->string('session_id', 64)->nullable()->index();
            $table->boolean('is_valid')->default(true)->index();
            $table->string('invalid_reason', 64)->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamps();

            $table->index(['surface', 'type', 'occurred_at']);
            $table->index(['product_id', 'type']);
            $table->index(['algorithm_key', 'occurred_at']);
        });

        /* Render telemetry for discovery surfaces (empty-rate, latency). */
        Schema::create('recommendation_render_stats', function (Blueprint $table) {
            $table->id();
            $table->string('surface', 64);
            $table->date('period_date');
            $table->unsignedInteger('renders')->default(0);
            $table->unsignedInteger('empty_renders')->default(0);
            $table->unsignedInteger('items_served')->default(0);
            $table->unsignedBigInteger('total_latency_ms')->default(0);
            $table->timestamps();

            $table->unique(['surface', 'period_date']);
        });

        /* Precomputed product associations (similar / complementary). */
        Schema::create('product_associations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_id')->index();
            $table->unsignedBigInteger('related_product_id')->index();
            $table->string('type', 20)->index(); // similar|complementary
            $table->decimal('score', 10, 5)->default(0);
            $table->unsignedInteger('support_count')->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'related_product_id', 'type']);
        });

        /* Automation engine — extend the existing workflows table. */
        Schema::table('workflows', function (Blueprint $table) {
            $table->string('key', 80)->nullable()->unique()->after('id');
            $table->string('action', 64)->nullable()->after('type');
            $table->json('definition')->nullable();
            $table->string('trigger_type', 32)->nullable();
            $table->json('retry_policy')->nullable();
            $table->unsignedInteger('timeout_seconds')->default(60);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
        });

        Schema::create('automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnDelete();
            $table->char('run_uuid', 36)->unique();
            $table->string('trigger', 64)->nullable();
            $table->string('status', 20)->default('queued')->index();
            $table->unsignedInteger('attempt')->default(0);
            $table->unsignedInteger('max_attempts')->default(3);
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->unsignedBigInteger('parent_run_id')->nullable();
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->timestamps();
        });

        Schema::create('automation_run_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id')->index();
            $table->string('level', 16)->default('info');
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        /* Marketplace intelligence metric snapshots. */
        Schema::create('marketplace_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('metric', 64)->index();
            $table->string('scope', 32)->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->date('period_date');
            $table->decimal('value', 18, 4)->default(0);
            $table->unsignedInteger('sample_size')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['metric', 'scope', 'scope_id', 'period_date'], 'metric_snapshot_unique');
            $table->index(['scope', 'scope_id', 'period_date']);
        });

        /* Anomaly detection rules. */
        Schema::create('marketplace_anomaly_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('metric', 64)->index();
            $table->string('method', 24)->default('zscore'); // threshold|zscore|iqr|percent_change
            $table->json('config')->nullable();
            $table->string('severity', 16)->default('warning');
            $table->string('scope', 32)->default('global');
            $table->unsignedInteger('window')->default(14);
            $table->unsignedInteger('min_sample')->default(5);
            $table->boolean('is_enabled')->default(true)->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        /* Detected anomalies. */
        Schema::create('marketplace_anomalies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rule_id')->nullable()->index();
            $table->string('dedup_key')->unique();
            $table->string('metric', 64)->index();
            $table->string('scope', 32)->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->decimal('observed_value', 18, 4)->nullable();
            $table->decimal('expected_value', 18, 4)->nullable();
            $table->decimal('deviation', 12, 4)->nullable();
            $table->string('severity', 16)->default('warning');
            $table->string('status', 20)->default('open')->index();
            $table->unsignedBigInteger('assigned_to')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('detected_at')->nullable()->index();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_anomalies');
        Schema::dropIfExists('marketplace_anomaly_rules');
        Schema::dropIfExists('marketplace_metric_snapshots');
        Schema::dropIfExists('recommendation_render_stats');
        Schema::dropIfExists('automation_run_logs');
        Schema::dropIfExists('automation_runs');

        Schema::table('workflows', function (Blueprint $table) {
            $table->dropColumn([
                'key', 'action', 'definition', 'trigger_type', 'retry_policy',
                'timeout_seconds', 'max_attempts', 'version', 'last_run_at', 'next_run_at',
            ]);
        });

        Schema::dropIfExists('product_associations');
        Schema::dropIfExists('recommendation_events');
        Schema::dropIfExists('algorithm_versions');
        Schema::dropIfExists('algorithms');
    }
};
