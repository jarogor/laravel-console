<?php

namespace App\Console;

use App\Console\Commands\LogProcessor;
use Illuminate\Console\Scheduling\Schedule;
use Ctl\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        LogProcessor::class
    ];

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     */
    protected function schedule(Schedule $schedule): void
    {

    }
}
