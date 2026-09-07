<?php

namespace App\Console\Commands;

use App\Security\Baseline\SecurityBaseline;
use App\Security\Validation\SecurityArchitectureValidator;
use Illuminate\Console\Command;

class SecurityValidateCommand extends Command
{
    protected $signature = 'security:validate {--audit : Include composer dependency audit}';

    protected $description = 'Validate security architecture baseline, static rules, and secret patterns';

    public function handle(
        SecurityArchitectureValidator $validator,
        SecurityBaseline $baseline,
    ): int {
        $this->line('Security Baseline v'.$baseline->version());

        $p0Count = count($baseline->rulesBySeverity('P0'));
        $p1Count = count($baseline->rulesBySeverity('P1'));
        $this->line("  Rules: P0={$p0Count} P1={$p1Count}");
        $this->newLine();

        $includeAudit = $this->option('audit') || config('security.dependency_audit_enabled', true);
        $violations = $validator->validate($includeAudit);

        foreach ($validator->warnings() as $warning) {
            $this->warn("  WARNING: {$warning}");
        }

        if ($violations === []) {
            $this->info('Security validation passed.');

            return self::SUCCESS;
        }

        $this->error('Security violations found:');
        foreach ($violations as $violation) {
            $this->line("  - {$violation}");
        }

        return self::FAILURE;
    }
}
