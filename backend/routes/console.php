<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('withdraw:auto-audit')->everyTenMinutes()->name('withdraw-auto-audit');
Schedule::command('withdraw:batch-process')->dailyAt('10:00')->name('withdraw-batch-process');
Schedule::command('withdraw:daily-reconcile')->dailyAt('02:00')->name('withdraw-daily-reconcile');

Schedule::command('withdrawal:reset-daily')->dailyAt('00:00')->name('withdrawal-reset-daily');
Schedule::command('withdrawal:auto-settle')->dailyAt('03:00')->name('withdrawal-auto-settle');
Schedule::command('withdrawal:notify-pending')->hourly()->name('withdrawal-notify-pending');
Schedule::command('withdrawal:clean-expired')->weeklyOn(0, '04:00')->name('withdrawal-clean-expired');
