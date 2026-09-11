<?php

namespace App\Architecture;

use App\Security\Validation\SecurityArchitectureValidator;

final class ArchitectureFitnessReport
{
    public function __construct(
        private readonly ArchitectureValidator $validator,
        private readonly ArchitectureDependencyGraph $graph,
        private readonly ArchitectureStaticAnalyzer $staticAnalyzer = new ArchitectureStaticAnalyzer,
        private readonly ComplexityGateChecker $complexity = new ComplexityGateChecker,
        private readonly FeatureContractValidator $featureContract = new FeatureContractValidator,
        private readonly SecurityFitnessChecker $security = new SecurityFitnessChecker,
        private readonly SecurityArchitectureValidator $securityArchitecture = new SecurityArchitectureValidator,
        private readonly IntelligenceGovernanceChecker $intelligence = new IntelligenceGovernanceChecker,
    ) {}

    /**
     * @return array<string, array{status: string, detail: string}>
     */
    public function categories(): array
    {
        $validatorViolations = $this->validator->validate();

        return [
            'domain_purity' => $this->category(
                $this->mergeFilters($validatorViolations, ['Domain must not', '[ARCH-001]', '[ARCH-003]', '[ARCH-004]']),
                'Domain has zero forbidden imports and FQCN references',
            ),
            'dependency_direction' => $this->category(
                $this->graph->validate(),
                'Layer imports follow Presentation → Application → Domain ← Infrastructure',
            ),
            'static_analysis' => $this->category(
                $this->staticAnalyzer->validate(),
                'No app()/resolve()/FQCN bypass of layer rules',
            ),
            'application_isolation' => $this->category(
                $this->mergeFilters($validatorViolations, ['Application must not', 'Handlers must']),
                'Application has no Http/Eloquent/DB dependencies',
            ),
            'controller_thinness' => $this->category(
                $this->mergeFilters($validatorViolations, ['Controller must not']),
                'Controllers delegate to handlers only',
            ),
            'complexity_gate' => $this->category(
                $this->complexity->validate(),
                'Handler line count and cyclomatic complexity within baseline',
            ),
            'feature_contract' => $this->category(
                $this->featureContract->validate(),
                'Commands/Queries have handlers, results, idempotency when required',
            ),
            'security_fitness' => $this->category(
                $this->security->validate(),
                'RLS migration, SchoolContext middleware, tenant isolation hooks',
            ),
            'security_architecture' => $this->category(
                $this->securityArchitecture->validate(),
                'Auth on API routes, policies, static security rules, secret scan, production block',
            ),
            'intelligence_governance' => $this->category(
                $this->intelligence->validate(),
                'Risk tiers, forbidden auto-actions, learning cannot escalate permissions',
            ),
            'legacy_writer_quarantine' => $this->category(
                $this->mergeFilters($validatorViolations, ['[ARCH-LEGACY-001]']),
                'Quarantined legacy Attendance writer is not reintroduced as a public write path',
            ),
        ];
    }

    public function allPass(): bool
    {
        return $this->validator->passes();
    }

    /**
     * @return list<string>
     */
    public function allViolations(): array
    {
        return array_values(array_unique($this->validator->validate()));
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->complexity->warnings();
    }

    /**
     * @param  list<string>  $violations
     * @param  list<string>  $needles
     * @return list<string>
     */
    private function mergeFilters(array $violations, array $needles): array
    {
        return array_values(array_filter(
            $violations,
            fn (string $v) => collect($needles)->contains(fn (string $n) => str_contains($v, $n)),
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
