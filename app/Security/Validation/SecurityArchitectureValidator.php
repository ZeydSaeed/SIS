<?php

namespace App\Security\Validation;

use App\Providers\SecurityServiceProvider;
use App\Security\Baseline\SecurityBaseline;
use DateTimeImmutable;

final class SecurityArchitectureValidator
{
    public function __construct(
        private readonly SecurityStaticAnalyzer $staticAnalyzer = new SecurityStaticAnalyzer,
        private readonly SecretScanner $secretScanner = new SecretScanner,
        private readonly SecurityBaseline $baseline = new SecurityBaseline,
    ) {}

    /** @var list<string> */
    private array $violations = [];

    /** @var list<string> */
    private array $warnings = [];

    /**
     * @return list<string>
     */
    public function validate(bool $includeDependencyAudit = false): array
    {
        $this->violations = [];
        $this->warnings = [];

        $this->validateBaselineExists();
        $this->validateExceptions();
        $this->validateProductionAutonomousBlock();
        $this->validateSecurityProvider();
        $this->validateStudentPolicy();

        foreach ($this->staticAnalyzer->validate() as $violation) {
            $this->violations[] = $violation;
        }

        foreach ($this->secretScanner->scan() as $violation) {
            $this->violations[] = $violation;
        }

        if ($includeDependencyAudit || config('security.dependency_audit_enabled', true)) {
            $this->validateDependencyAudit();
        }

        return array_values(array_unique($this->violations));
    }

    /**
     * @return list<string>
     */
    public function warnings(): array
    {
        return $this->warnings;
    }

    public function passes(bool $includeDependencyAudit = false): bool
    {
        return $this->validate($includeDependencyAudit) === [];
    }

    private function validateBaselineExists(): void
    {
        if (! is_file(base_path('.cursor/security/SECURITY-BASELINE.json'))) {
            $this->violations[] = '[SEC-SSOT-001] SECURITY-BASELINE.json missing from .cursor/security/';
        }

        if ($this->baseline->version() === '0') {
            $this->violations[] = '[SEC-SSOT-002] SECURITY-BASELINE.json is empty or invalid';
        }
    }

    private function validateExceptions(): void
    {
        $path = base_path('.cursor/security/SECURITY-EXCEPTIONS.yaml');
        if (! is_file($path)) {
            $this->violations[] = '[SEC-EXCEPTION-001] SECURITY-EXCEPTIONS.yaml missing';

            return;
        }

        /** @var array{exceptions?: list<array<string, mixed>>} $parsed */
        $content = (string) file_get_contents($path);
        if (preg_match_all('/expires_at:\s*([^\s#]+)/', $content, $matches)) {
            foreach ($matches[1] as $expiresAt) {
                if (! is_string($expiresAt) || $expiresAt === '') {
                    $this->violations[] = '[SEC-EXCEPTION-002] Security exception missing expires_at';

                    continue;
                }

                $expiry = new DateTimeImmutable($expiresAt);
                if ($expiry < new DateTimeImmutable('today')) {
                    $this->violations[] = "[SEC-EXCEPTION-003] Security exception expired on {$expiresAt}";
                }
            }
        }
    }

    private function validateProductionAutonomousBlock(): void
    {
        if (! config('optimization.autonomous.block_production', true)) {
            $this->violations[] = '[SEC-INTEL-002] optimization.autonomous.block_production must remain enabled';
        }

        $source = (string) file_get_contents(app_path('Optimization/SelfHealing/AutonomousExecutionPolicy.php'));
        if (! str_contains($source, 'isProductionAutonomousBlocked')) {
            $this->violations[] = '[SEC-INTEL-002] AutonomousExecutionPolicy production block missing';
        }

        if (! str_contains($source, "config('app.env') === 'production'")) {
            $this->violations[] = '[SEC-INTEL-002] Production environment must block autonomous execution';
        }
    }

    private function validateSecurityProvider(): void
    {
        $providers = require base_path('bootstrap/providers.php');

        if (! in_array(SecurityServiceProvider::class, $providers, true)) {
            $this->violations[] = '[SEC-RUNTIME-001] SecurityServiceProvider must be registered in bootstrap/providers.php';
        }
    }

    private function validateStudentPolicy(): void
    {
        if (! is_file(app_path('Security/Policies/StudentPolicy.php'))) {
            $this->violations[] = '[SEC-AUTHZ-001] StudentPolicy missing';
        }
    }

    private function validateDependencyAudit(): void
    {
        if (! config('security.dependency_audit_enabled', true)) {
            $this->warnings[] = '[SEC-DEP-001] Dependency audit disabled via config';

            return;
        }

        $result = (new ComposerAuditGate)->evaluate();
        if (! $result['passed']) {
            $this->violations[] = '[SEC-DEP-001] Composer audit blocking policy failed (exit '.$result['exit_code'].', blocking='.$result['blocking_count'].')';
        }
    }
}
