<?php

namespace App\Console\Commands;

use App\Models\HomepageSection;
use Illuminate\Console\Command;

class ExpireHomepageSections extends Command
{
    protected $signature = 'homepage:expire-sections';

    protected $description = 'Delete homepage sections whose scheduled end time has passed and are marked for automatic deletion.';

    public function handle(): int
    {
        $deleted = HomepageSection::purgeExpired();

        $this->info("Expired homepage sections removed: {$deleted}");

        return Command::SUCCESS;
    }
}
