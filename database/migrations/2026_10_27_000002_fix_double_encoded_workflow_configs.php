<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The four seeded approval workflows stored their config double-encoded (a
 * JSON string inside a JSON column), so `$workflow->config` resolved to a
 * string instead of an array. Steps then passed a string where every action
 * expects an array input, and `$config['push_fleetbase']` and friends read as
 * null — which is why these workflows never actually ran.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('workflows')->orderBy('id')->get()->each(function ($row): void {
            $once = json_decode((string) $row->config, true);

            if (! is_string($once)) {
                return;
            }

            $twice = json_decode($once, true);

            if (! is_array($twice)) {
                return;
            }

            DB::table('workflows')->where('id', $row->id)->update([
                'config' => json_encode($twice),
            ]);
        });
    }

    public function down(): void
    {
        //
    }
};
