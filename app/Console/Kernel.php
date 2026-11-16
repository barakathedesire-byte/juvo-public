<?php

namespace App\Console;

use App\Console\Commands\DataImport;
use App\Console\Commands\Juvo;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Juvo::class,
        DataImport::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('ads:advance')->hourly();
        $schedule->command('ads:optimize')->dailyAt('03:00');
        $schedule->command('ads:reconcile')->hourly();

        $schedule->command('recommendations:rebuild')->dailyAt('04:00');
        $schedule->command('intelligence:run')->dailyAt('01:00');
        $schedule->command('automation:run')->everyFiveMinutes();
        $schedule->command('homepage:expire-sections')->everyFiveMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
