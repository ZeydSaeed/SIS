<?php

namespace App\Optimization\Commands;

use App\Optimization\Baseline\BaselineSnapshotService;
use App\Optimization\SelfHealing\AdaptiveBaselineEngine;
use Illuminate\Console\Command;

class OptimizationBaselineCommand extends Command
{
    protected $signature = 'optimization:baseline {--capture : Capture a new DB baseline snapshot}';

    protected $description = 'Show adaptive baseline or capture a new baseline snapshot';

    public function handle(
        AdaptiveBaselineEngine $adaptive,
        BaselineSnapshotService $snapshotService,
    ): int {
        if ($this->option('capture')) {
            $snapshot = $snapshotService->capture('manual');
            $this->info('Baseline snapshot captured: '.$snapshot->baseline_code);

            return self::SUCCESS;
        }

        $baseline = $adaptive->load();
        $this->info('Adaptive Baseline');
        $this->line('Updated: '.($baseline['updated_at'] ?? 'never'));
        $this->line(json_encode($baseline['metrics'] ?? [], JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
