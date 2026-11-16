<?php

namespace App\Console\Commands;

use App\Services\Intelligence\ProductAssociationService;
use Illuminate\Console\Command;

class RecommendationsRebuild extends Command
{
    protected $signature = 'recommendations:rebuild {--min-support=2}';

    protected $description = 'Rebuild similar/complementary product associations for the discovery engine.';

    public function handle(ProductAssociationService $associations): int
    {
        $result = $associations->recompute((int) $this->option('min-support'));

        $this->info("Associations rebuilt: {$result['similar']} similar, {$result['complementary']} complementary.");

        return self::SUCCESS;
    }
}
