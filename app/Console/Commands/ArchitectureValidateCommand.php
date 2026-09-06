<?php

namespace App\Console\Commands;

use App\Architecture\ArchitectureFitnessReport;
use App\Architecture\ArchitectureValidator;
use Illuminate\Console\Command;

class ArchitectureValidateCommand extends Command
{
    protected $signature = 'architecture:validate {--fitness : Show fitness category summary}';

    protected $description = 'Validate Clean Architecture layer rules and dependency direction';

    public function handle(
        ArchitectureValidator $validator,
        ArchitectureFitnessReport $fitness,
    ): int {
        $violations = $validator->validate();

        if ($this->option('fitness') || $violations !== []) {
            $this->line('Architecture Fitness (baseline v'.$validator->baseline()->version().')');
            foreach ($fitness->categories() as $name => $category) {
                $mark = $category['status'] === 'PASS' ? 'PASS' : 'FAIL';
                $this->line("  {$name}: {$mark} — {$category['detail']}");
            }
            $this->newLine();
        }

        if ($violations === []) {
            $this->info('Architecture validation passed.');

            return self::SUCCESS;
        }

        $this->error('Architecture violations found:');
        foreach ($violations as $violation) {
            $this->line("  - {$violation}");
        }

        return self::FAILURE;
    }
}
