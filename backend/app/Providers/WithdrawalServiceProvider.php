<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class WithdrawalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                \App\Console\Commands\AutoSettleWithdrawals::class,
                \App\Console\Commands\BatchProcessWithdrawals::class,
                \App\Console\Commands\NotifyPendingWithdrawals::class,
                \App\Console\Commands\CleanExpiredWithdrawals::class,
                \App\Console\Commands\ResetDailyWithdrawn::class,
            ]);
        }
    }
}
