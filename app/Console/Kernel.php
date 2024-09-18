<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        $schedule->call('App\Http\Controllers\CronJobController@execute_send_lass_there_than_3_days')->dailyAt("10:00");
        $schedule->call('App\Http\Controllers\CronJobController@execute_send_expired_products')->everyFiveMinutes();
        $schedule->call('App\Http\Controllers\CronJobController@execute_send_useage_more_than_85_percent')->everyFourMinutes();

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
