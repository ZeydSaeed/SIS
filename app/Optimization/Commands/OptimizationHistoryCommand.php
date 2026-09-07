<?php

namespace App\Optimization\Commands;

use App\Optimization\Memory\OptimizationHistoryRecorder;
use Illuminate\Console\Command;

class OptimizationHistoryCommand extends Command
{
    protected $signature = 'optimization:history {--limit=10 : Number of records to show}';

    protected $description = 'Show recent optimization history records';

    public function handle(OptimizationHistoryRecorder $history): int
    {
        $records = $history->recent((int) $this->option('limit'));

        if ($records === []) {
            $this->info('No optimization history records found.');

            return self::SUCCESS;
        }

        foreach ($records as $record) {
            $this->line('---');
            $this->line('ID: '.($record['id'] ?? 'n/a'));
            $this->line('Decision: '.($record['decision'] ?? 'n/a'));
            $this->line('Component: '.($record['component'] ?? 'n/a'));
            $this->line('Recorded: '.($record['recorded_at'] ?? 'n/a'));
        }

        return self::SUCCESS;
    }
}
