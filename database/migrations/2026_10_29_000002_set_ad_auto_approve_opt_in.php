<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ad auto-approval is a monetisation control with real consequences, so it
     * now defaults to opt-in. Only the untouched seeded default (value '1',
     * version 1) is flipped; any operator edit is left alone.
     */
    public function up(): void
    {
        $rule = DB::table('business_rules')
            ->where('key', 'ad_auto_approve')
            ->where('value', '1')
            ->where('version', 1)
            ->first();

        if (! $rule) {
            return;
        }

        DB::table('business_rules')->where('id', $rule->id)->update([
            'value' => '0',
            'version' => 2,
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('business_rule_revisions')) {
            DB::table('business_rule_revisions')->insert([
                'business_rule_id' => $rule->id,
                'key' => 'ad_auto_approve',
                'version' => 2,
                'value' => '0',
                'is_active' => (bool) $rule->is_active,
                'effective_from' => $rule->effective_from ?? null,
                'effective_to' => $rule->effective_to ?? null,
                'changed_by' => null,
                'reason' => 'Governance: ad auto-approval set to opt-in (safe default).',
                'snapshot' => json_encode(['key' => 'ad_auto_approve', 'value' => '0', 'version' => 2]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('business_rules')
            ->where('key', 'ad_auto_approve')
            ->where('value', '0')
            ->where('version', 2)
            ->update(['value' => '1', 'version' => 3, 'updated_at' => now()]);
    }
};
