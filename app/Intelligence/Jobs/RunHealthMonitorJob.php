<?php

namespace App\Intelligence\Jobs;

use App\Intelligence\Guardian\DatabaseGuardian;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunHealthMonitorJob implements ShouldQueue
{
    use Queueable;

    public function handle(DatabaseGuardian $guardian): void
    {
        if (! config('intelligence.enabled', true)) {
            return;
        }

        $guardian->runHealthCycle();
    }
}
