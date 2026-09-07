<?php

namespace App\Optimization\Gates;

use App\Architecture\ArchitectureValidator;

final class ArchitectureOptimizationGate
{
    public function __construct(
        private readonly ArchitectureValidator $validator,
    ) {}

    public function passes(): bool
    {
        if (! config('optimization.gates.architecture_validate_before_code_change', true)) {
            return true;
        }

        return $this->validator->passes();
    }

    public function assertPasses(): void
    {
        if (! $this->passes()) {
            throw new \RuntimeException(
                'Architecture optimization gate failed. Run php artisan architecture:validate --fitness before applying code changes.'
            );
        }
    }
}
