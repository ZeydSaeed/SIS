<?php

namespace App\Console\Commands;

use App\Architecture\FeatureContractValidator;
use Illuminate\Console\Command;

class ArchitectureFeatureCheckCommand extends Command
{
    protected $signature = 'architecture:feature-check {context? : Bounded context e.g. Enrollment}';

    protected $description = 'Validate feature contract (handlers, results, idempotency) for a bounded context';

    public function handle(FeatureContractValidator $contract): int
    {
        $context = $this->argument('context');
        $violations = $contract->validate(is_string($context) ? $context : null);

        if ($context !== null) {
            $this->info("Feature contract: {$context}");
            $this->table(['Type', 'Item'], collect($contract->contractFor((string) $context))
                ->flatMap(fn (array $items, string $type) => collect($items)->map(fn (string $item) => [$type, $item]))
                ->all());
            $this->newLine();
        }

        if ($violations === []) {
            $this->info('Feature contract validation passed.');

            return self::SUCCESS;
        }

        $this->error('Feature contract violations:');
        foreach ($violations as $violation) {
            $this->line("  - {$violation}");
        }

        return self::FAILURE;
    }
}
