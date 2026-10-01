<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Console\Commands\ImportProducts;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        ImportProducts::class,
    ];

    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('leave:accrue')->monthly();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
