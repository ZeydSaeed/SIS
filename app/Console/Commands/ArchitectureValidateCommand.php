<?php

namespace App\Console\Commands;

use App\Architecture\ArchitectureValidator;
use Illuminate\Console\Command;

class ArchitectureValidateCommand extends Command
{
    protected $signature = 'architecture:validate';

    protected $description = 'Validate Clean Architecture layer rules (Domain, Application, Controllers)';

    public function handle(ArchitectureValidator $validator): int
    {
        $violations = $validator->validate();

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
