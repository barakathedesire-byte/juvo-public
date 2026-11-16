<?php

namespace App\Console\Commands;

use App\Services\Rules\RuleRegistry;
use Illuminate\Console\Command;

class RulesSync extends Command
{
    protected $signature = 'rules:sync';

    protected $description = 'Synchronise business-rule governance metadata (owner, schema, safe default, effective window) from the registry.';

    public function handle(RuleRegistry $registry): int
    {
        $count = $registry->sync();

        $this->info("Business rule registry synchronised: {$count} rules.");

        return self::SUCCESS;
    }
}
