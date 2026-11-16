<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `min_ad_budget` is the deprecated duplicate of `ad_min_budget`. It used to
     * be seeded with the same label as the canonical rule, so the admin screen
     * showed two identical "Minimum ad budget" rows with different values.
     *
     * The legacy row is relabelled to match the registry and mirrors the
     * canonical value, so a stale reader of the legacy key never disagrees with
     * what operators see and configure.
     */
    public function up(): void
    {
        if (! Schema::hasTable('business_rules')) {
            return;
        }

        $legacy = DB::table('business_rules')->where('key', 'min_ad_budget')->first();

        if (! $legacy) {
            return;
        }

        $canonical = DB::table('business_rules')->where('key', 'ad_min_budget')->first();

        $updates = [
            'name' => 'Minimum ad budget (legacy key)',
            'description' => 'Deprecated duplicate of ad_min_budget, kept in sync for older readers. Prefer ad_min_budget.',
            'updated_at' => now(),
        ];

        if ($canonical && $canonical->value !== null && $canonical->value !== '') {
            $updates['value'] = $canonical->value;
        }

        DB::table('business_rules')->where('key', 'min_ad_budget')->update($updates);
    }

    public function down(): void
    {
        if (! Schema::hasTable('business_rules')) {
            return;
        }

        DB::table('business_rules')->where('key', 'min_ad_budget')->update([
            'name' => 'Minimum ad budget',
            'updated_at' => now(),
        ]);
    }
};
