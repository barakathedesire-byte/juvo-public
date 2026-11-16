<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* ---------------- Placements / inventory ---------------- */
        Schema::create('ad_placements', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->decimal('base_price', 12, 2)->default(0);
            $table->string('pricing_model')->default('cpm'); // cpm | cpc | flat
            $table->unsignedInteger('max_active')->default(0); // 0 = unlimited
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        /* ---------------- Packages ---------------- */
        Schema::create('ad_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedBigInteger('placement_id')->nullable();
            $table->unsignedInteger('duration_days')->default(30);
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedBigInteger('impressions_included')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('placement_id')->references('id')->on('ad_placements')->nullOnDelete();
        });

        /* ---------------- Creative versions ---------------- */
        Schema::create('ad_creatives', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedInteger('version')->default(1);
            $table->string('headline')->nullable();
            $table->text('body')->nullable();
            $table->string('image')->nullable();
            $table->string('target_url')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('ad_campaigns')->cascadeOnDelete();
        });

        /* ---------------- Moderation / audit log ---------------- */
        Schema::create('ad_moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();
            $table->string('action');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('ad_campaigns')->cascadeOnDelete();
        });

        /* ---------------- Raw event stream ---------------- */
        Schema::create('ad_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('placement_id')->nullable();
            $table->string('type'); // impression | click | conversion
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('campaign_id')->references('id')->on('ad_campaigns')->cascadeOnDelete();
            $table->index(['campaign_id', 'type']);
        });

        /* ---------------- Daily aggregates ---------------- */
        Schema::create('ad_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->date('date');
            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->decimal('spend', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('ad_campaigns')->cascadeOnDelete();
            $table->unique(['campaign_id', 'date']);
        });

        /* ---------------- Extend ad_campaigns ---------------- */
        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('placement_id')->nullable()->after('product_id');
            $table->unsignedBigInteger('package_id')->nullable()->after('placement_id');
            $table->string('objective')->nullable()->after('title');       // awareness | traffic | conversions
            $table->string('billing_model')->default('cpm')->after('objective');
            $table->string('creative_headline')->nullable()->after('billing_model');
            $table->text('creative_body')->nullable()->after('creative_headline');
            $table->string('creative_image')->nullable()->after('creative_body');
            $table->string('target_url')->nullable()->after('creative_image');
            $table->unsignedBigInteger('impressions')->default(0)->after('amount_paid');
            $table->unsignedBigInteger('clicks')->default(0)->after('impressions');
            $table->unsignedBigInteger('conversions')->default(0)->after('clicks');
            $table->decimal('spent', 12, 2)->default(0)->after('conversions');
            $table->timestamp('submitted_at')->nullable()->after('paid_at');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
            $table->tinyInteger('priority')->default(0)->after('admin_note');
        });

        // Widen status enum -> varchar so the full lifecycle fits.
        DB::statement("ALTER TABLE ad_campaigns MODIFY status VARCHAR(32) NOT NULL DEFAULT 'draft'");
        DB::statement('ALTER TABLE ad_campaigns MODIFY COLUMN objective VARCHAR(32) NULL');

        /* ---------------- Vendor advertising account flags ---------------- */
        Schema::table('vendors', function (Blueprint $table) {
            if (! Schema::hasColumn('vendors', 'ad_restricted')) {
                $table->boolean('ad_restricted')->default(false)->after('is_approved');
            }
            if (! Schema::hasColumn('vendors', 'ad_credit_limit')) {
                $table->decimal('ad_credit_limit', 12, 2)->default(0)->after('ad_restricted');
            }
        });

        /* ---------------- Default placements ---------------- */
        $now = now();
        $placements = [
            ['key' => 'homepage_hero', 'name' => 'Homepage Hero Banner', 'width' => 1200, 'height' => 320, 'base_price' => 15000, 'pricing_model' => 'flat'],
            ['key' => 'homepage_strip', 'name' => 'Homepage Strip', 'width' => 970, 'height' => 90, 'base_price' => 8000, 'pricing_model' => 'cpm'],
            ['key' => 'category_top', 'name' => 'Category Top', 'width' => 970, 'height' => 250, 'base_price' => 6000, 'pricing_model' => 'cpm'],
            ['key' => 'product_sidebar', 'name' => 'Product Page Sidebar', 'width' => 300, 'height' => 600, 'base_price' => 5000, 'pricing_model' => 'cpm'],
            ['key' => 'search_sponsored', 'name' => 'Sponsored Search Result', 'width' => null, 'height' => null, 'base_price' => 250, 'pricing_model' => 'cpc'],
        ];
        foreach ($placements as $i => $p) {
            DB::table('ad_placements')->updateOrInsert(
                ['key' => $p['key']],
                $p + ['description' => null, 'max_active' => 0, 'is_active' => true, 'sort_order' => $i, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        /* ---------------- Default packages ---------------- */
        $starterPlacement = DB::table('ad_placements')->where('key', 'homepage_strip')->value('id');
        $proPlacement = DB::table('ad_placements')->where('key', 'homepage_hero')->value('id');
        $packages = [
            ['name' => 'Starter', 'placement_id' => $starterPlacement, 'duration_days' => 7, 'price' => 10000, 'impressions_included' => 20000, 'sort_order' => 0],
            ['name' => 'Growth', 'placement_id' => $starterPlacement, 'duration_days' => 14, 'price' => 25000, 'impressions_included' => 60000, 'sort_order' => 1],
            ['name' => 'Premium', 'placement_id' => $proPlacement, 'duration_days' => 30, 'price' => 60000, 'impressions_included' => 200000, 'sort_order' => 2],
        ];
        foreach ($packages as $p) {
            DB::table('ad_packages')->updateOrInsert(
                ['name' => $p['name']],
                $p + ['description' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        /* ---------------- Default ad settings (business rules) ---------------- */
        $rules = [
            ['key' => 'ad_enabled', 'name' => 'Advertising enabled', 'value_type' => 'boolean', 'value' => '1'],
            ['key' => 'ad_auto_approve', 'name' => 'Auto-approve ad submissions', 'value_type' => 'boolean', 'value' => '0'],
            ['key' => 'ad_default_billing_model', 'name' => 'Default billing model', 'value_type' => 'select', 'value' => 'cpm', 'options' => 'cpm,cpc,flat'],
            ['key' => 'ad_max_active_per_vendor', 'name' => 'Max active campaigns per vendor', 'value_type' => 'number', 'value' => '5'],
            ['key' => 'ad_platform_take_rate', 'name' => 'Platform take rate (%)', 'value_type' => 'number', 'value' => '0'],
            ['key' => 'ad_min_budget', 'name' => 'Minimum ad budget', 'value_type' => 'number', 'value' => '1000'],
        ];
        foreach ($rules as $r) {
            DB::table('business_rules')->updateOrInsert(
                ['key' => $r['key']],
                $r + ['description' => null, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_daily_stats');
        Schema::dropIfExists('ad_events');
        Schema::dropIfExists('ad_moderation_logs');
        Schema::dropIfExists('ad_creatives');
        Schema::dropIfExists('ad_packages');
        Schema::dropIfExists('ad_placements');

        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'placement_id', 'package_id', 'objective', 'billing_model', 'creative_headline',
                'creative_body', 'creative_image', 'target_url', 'impressions', 'clicks',
                'conversions', 'spent', 'submitted_at', 'reviewed_at', 'reviewed_by', 'priority',
            ]);
        });

        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'ad_restricted')) {
                $table->dropColumn('ad_restricted');
            }
            if (Schema::hasColumn('vendors', 'ad_credit_limit')) {
                $table->dropColumn('ad_credit_limit');
            }
        });
    }
};
