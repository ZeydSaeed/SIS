<?php

namespace App\Optimization\Commands;

use App\Optimization\SelfHealing\SelfHealingPerformanceEngine;
use Illuminate\Console\Command;

class OptimizationStatusCommand extends Command
{
    protected $signature = 'optimization:status';

    protected $description = 'Show self-healing engine status, mode, and safe-mode state';

    public function handle(SelfHealingPerformanceEngine $engine): int
    {
        $status = $engine->status();

        $this->info('Self-Healing Performance Engine Status');
        $this->table(['Key', 'Value'], collect($status)->map(fn ($v, $k) => [$k, is_array($v) ? json_encode($v) : (string) $v])->values()->all());

        return self::SUCCESS;
    }
}
