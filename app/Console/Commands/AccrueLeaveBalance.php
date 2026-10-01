<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeaveBalance;

class AccrueLeaveBalance extends Command
{
    protected $signature = 'leave:accrue';
    protected $description = 'Add 1.25 days of leave to each staff leave balance';

    public function handle()
    {
        LeaveBalance::all()->each(function ($balance) {
            $balance->increment('balance', 1.25);
        });

        $this->info('Leave balances accrued!');
    }
}
