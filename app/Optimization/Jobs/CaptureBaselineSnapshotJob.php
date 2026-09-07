<?php

namespace App\Optimization\Jobs;

use App\Optimization\Baseline\BaselineSnapshotService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class CaptureBaselineSnapshotJob implements ShouldQueue
{
    use Queueable;

    public function handle(BaselineSnapshotService $baselineService): void
    {
        if (! config('optimization.enabled', true)) {
            return;
        }

        $baselineService->capture('scheduled');
    }
}
