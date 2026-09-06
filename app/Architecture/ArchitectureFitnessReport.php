<?php

namespace App\Architecture;

final class ArchitectureFitnessReport
{
    public function __construct(
        private readonly ArchitectureValidator $validator,
        private readonly ArchitectureDependencyGraph $graph,
    ) {}

    /**
     * @return array<string, array{status: string, detail: string}>
     */
    public function categories(): array
    {
        $validatorViolations = $this->validator->validate();
        $graphViolations = $this->graph->validate();

        return [
            'domain_purity' => $this->category(
                $this->filterViolations($validatorViolations, 'Domain must not'),
                'Domain has zero Laravel/Application/Infrastructure imports',
            ),
            'dependency_direction' => $this->category(
                $graphViolations,
                'Layer imports follow Presentation → Application → Domain ← Infrastructure',
            ),
            'application_isolation' => $this->category(
                $this->filterViolations($validatorViolations, 'Application must not'),
                'Application has no Http/Eloquent/DB dependencies',
            ),
            'controller_thinness' => $this->category(
                $this->filterViolations($validatorViolations, 'Controller must not'),
                'Controllers delegate to handlers only',
            ),
            'handler_rules' => $this->category(
                $this->filterViolations($validatorViolations, 'Handlers must'),
                'Handlers use UnitOfWork and repository ports',
            ),
            'intelligence_alignment' => $this->category(
                $this->filterViolations($validatorViolations, 'Intelligence'),
                'Intelligence HTTP entry uses Application handlers',
            ),
        ];
    }

    public function allPass(): bool
    {
        return $this->validator->passes() && $this->graph->passes();
    }

    /**
     * @return list<string>
     */
    public function allViolations(): array
    {
        return array_values(array_unique([
            ...$this->validator->validate(),
            ...$this->graph->validate(),
        ]));
    }

    /**
     * @param  list<string>  $violations
     * @return list<string>
     */
    private function filterViolations(array $violations, string $needle): array
    {
        return array_values(array_filter(
            $violations,
            fn (string $v) => str_contains($v, $needle),
        ));
    }

    /**
     * @param  list<string>  $violations
     * @return array{status: string, detail: string}
     */
    private function category(array $violations, string $detail): array
    {
        return [
            'status' => $violations === [] ? 'PASS' : 'FAIL',
            'detail' => $detail,
        ];
    }
}
