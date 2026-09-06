<?php

namespace App\Console\Commands;

use App\Architecture\ArchitectureDependencyGraph;
use App\Architecture\ArchitectureFitnessReport;
use Illuminate\Console\Command;

class ArchitectureGraphCommand extends Command
{
    protected $signature = 'architecture:graph {--json : Output dependency edges as JSON}';

    protected $description = 'Display allowed dependency graph and current layer violations';

    public function handle(
        ArchitectureDependencyGraph $graph,
        ArchitectureFitnessReport $fitness,
    ): int {
        if ($this->option('json')) {
            $this->line(json_encode([
                'diagram' => $graph->diagram(),
                'edges' => $graph->edges(),
                'violations' => $graph->validate(),
                'fitness' => $fitness->categories(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $fitness->allPass() ? self::SUCCESS : self::FAILURE;
        }

        $this->line($graph->diagram());
        $this->newLine();
        $this->info('Architecture Fitness');

        foreach ($fitness->categories() as $name => $category) {
            $status = $category['status'] === 'PASS' ? '<fg=green>PASS</>' : '<fg=red>FAIL</>';
            $this->line(sprintf('  %-24s %s  %s', $name, $status, $category['detail']));
        }

        $violations = $fitness->allViolations();
        $this->newLine();

        if ($violations === []) {
            $this->info('No dependency violations detected.');

            return self::SUCCESS;
        }

        $this->error(count($violations).' violation(s):');
        foreach ($violations as $violation) {
            $this->line("  - {$violation}");
        }

        return self::FAILURE;
    }
}
