<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('profiles:promote-academic-year')->withoutOverlapping()
            ->yearlyOn(1, 5, '00:00'); // promote users

        $schedule->command('app:deactivate-passed-out-users')->withoutOverlapping()
            ->yearlyOn(1, 5, '00:15'); // deactivates the passed out students

        $schedule->command('approvals:assign-reviewers')
            ->dailyAt('00:30');

        $schedule->command('events:update-statuses')
            ->hourly();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
    }
}
