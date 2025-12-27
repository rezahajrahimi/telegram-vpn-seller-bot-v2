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
        $schedule->command('queue:work --stop-when-empty')->everyMinute();
        $schedule->call('App\Http\Controllers\CronJobController@execute_send_lass_there_than_3_days')->dailyAt("12:00");
        $schedule->call('App\Http\Controllers\CronJobController@execute_send_expired_products')->everyFiveMinutes();
        $schedule->call('App\Http\Controllers\CronJobController@execute_send_useage_more_than_85_percent')->everyFourMinutes();
        $schedule->call('App\Http\Controllers\CronJobController@execute_create_daily_backup')->everyThreeHours();
        $schedule->call('App\Http\Controllers\CronJobController@calculate_product_category_price_by_tether')->everyFiveMinutes();
        $schedule->call('App\Http\Controllers\CronJobController@calculate_product_category_price_in_dollar_by_toman')->everyFiveMinutes();
        $schedule->call('App\Http\Controllers\CronJobController@execute_auto_delete_expired_configs')->dailyAt("08:02");

    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
