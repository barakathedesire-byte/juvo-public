<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mobile money details for an ad campaign (network, msisdn, aggregator
     * reference and charge status). Without them an admin has no way to
     * reconcile a mobile money campaign that is awaiting verification.
     */
    public function up(): void
    {
        if (! Schema::hasTable('ad_campaigns') || Schema::hasColumn('ad_campaigns', 'payment_meta')) {
            return;
        }

        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->json('payment_meta')->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ad_campaigns') || ! Schema::hasColumn('ad_campaigns', 'payment_meta')) {
            return;
        }

        Schema::table('ad_campaigns', function (Blueprint $table) {
            $table->dropColumn('payment_meta');
        });
    }
};
