<?php

namespace App\Architecture;

final class IntelligenceGovernanceChecker
{
    public function __construct(
        private readonly ArchitectureBaseline $baseline = new ArchitectureBaseline,
    ) {}

    /** @var list<string> */
    private array $violations = [];

    /**
     * @return list<string>
     */
    public function validate(): array
    {
        $this->violations = [];

        $gov = $this->baseline->intelligenceGovernance();
        $maxTier = (int) config('intelligence.auto_execute_max_tier', 1);
        $baselineMax = (int) ($gov['max_auto_execute_tier'] ?? 1);

        if ($maxTier > $baselineMax) {
            $this->violations[] = "[INT-001] auto_execute_max_tier={$maxTier} exceeds baseline max {$baselineMax} — self-escalation forbidden";
        }

        $forbidden = config('intelligence.forbidden_auto_actions', []);
        foreach ($gov['forbidden_auto_actions_minimum'] ?? [] as $action) {
            if (! in_array($action, $forbidden, true)) {
                $this->violations[] = "[INT-002] forbidden_auto_actions must include '{$action}'";
            }
        }

        $learningPaths = [
            app_path('Intelligence/Learning'),
            app_path('Intelligence/Optimization'),
        ];

        foreach ($learningPaths as $dir) {
            if (! is_dir($dir)) {
                continue;
            }

            foreach ($this->phpFiles($dir) as $file) {
                $content = (string) file_get_contents($file->getPathname());
                foreach ($gov['learning_must_not_modify'] ?? [] as $protected) {
                    if (preg_match('/config\s*\(\s*[\'"]intelligence\.(auto_execute_max_tier|forbidden_auto_actions)/', $content) === 1) {
                        $this->violations[] = "{$this->relative($file)}: [INT-003] Learning layer must not modify intelligence permissions at runtime";
                    }
                    if (str_contains($content, '$protected') && str_contains($content, '=')) {
                        // noop — too broad
                    }
                }

                if (preg_match('/auto_execute_max_tier\s*=\s*/', $content) === 1
                    || preg_match('/forbidden_auto_actions\s*\[\]\s*=/', $content) === 1) {
                    $this->violations[] = "{$this->relative($file)}: [INT-004] Intelligence learning must not escalate auto-execute permissions";
                }
            }
        }

        if (! is_file(app_path('Intelligence/Governance/RiskPolicy.php'))) {
            $this->violations[] = '[INT-005] RiskPolicy governance class missing';
        }

        if (! is_file(app_path('Intelligence/Governance/ApprovalGate.php'))) {
            $this->violations[] = '[INT-006] ApprovalGate required for human-in-the-loop';
        }

        if (! is_file(app_path('Application/Intelligence/Commands/ApproveRecommendationHandler.php'))) {
            $this->violations[] = '[INT-007] Intelligence approve flow must use Application handler';
        }

        return $this->violations;
    }

    /**
     * @return list<\SplFileInfo>
     */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file;
            }
        }

        return $files;
    }

    private function relative(\SplFileInfo $file): string
    {
        return str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
    }
}
