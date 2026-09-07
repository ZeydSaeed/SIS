<?php

namespace App\Console\Commands;

use App\Security\Baseline\SecurityBaseline;
use Illuminate\Console\Command;

class SecurityBaselineCommand extends Command
{
    protected $signature = 'security:baseline';

    protected $description = 'Display security baseline summary from SSOT';

    public function handle(SecurityBaseline $baseline): int
    {
        $this->info('Security Baseline v'.$baseline->version());
        $this->newLine();

        foreach (['P0', 'P1', 'P2', 'P3'] as $severity) {
            $rules = $baseline->rulesBySeverity($severity);
            $this->line("{$severity}: ".count($rules).' rules');
        }

        return self::SUCCESS;
    }
}
