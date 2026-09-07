<?php

namespace App\Optimization;

use App\Intelligence\Guardian\DatabaseGuardian;
use App\Intelligence\Models\Recommendation;
use App\Optimization\Analysis\BottleneckAnalyzer;
use App\Optimization\Analysis\OptimizationScorer;
use App\Optimization\Baseline\BaselineSnapshotService;
use App\Optimization\Enums\OptimizationMode;
use App\Optimization\Execution\IsolatedOptimizationRunner;
use App\Optimization\Memory\OptimizationHistoryRecorder;
use Illuminate\Support\Facades\File;

final class OptimizationEngine
{
    public function __construct(
        private readonly DatabaseGuardian $guardian,
        private readonly BaselineSnapshotService $baselineService,
        private readonly BottleneckAnalyzer $bottleneckAnalyzer,
        private readonly OptimizationScorer $scorer,
        private readonly IsolatedOptimizationRunner $runner,
        private readonly OptimizationHistoryRecorder $history,
    ) {}

    public function mode(): OptimizationMode
    {
        return OptimizationMode::tryFrom(config('optimization.mode', 'observe'))
            ?? OptimizationMode::Observe;
    }

    /**
     * Level 0 — Observe: collect metrics, capture baseline, NO changes.
     *
     * @return array<string, mixed>
     */
    public function observe(): array
    {
        $health = $this->guardian->runHealthCycle();
        $baseline = $this->baselineService->capture('observe-cycle');

        return [
            'mode' => OptimizationMode::Observe->value,
            'level' => 0,
            'health' => $health,
            'baseline_code' => $baseline->baseline_code,
            'message' => 'Observe complete — no modifications applied',
        ];
    }

    /**
     * Level 1 — Recommend: analyze bottlenecks, score candidates, write reports.
     *
     * @return array<string, mixed>
     */
    public function recommend(): array
    {
        $this->guardian->runPerformanceCycle();
        $bottlenecks = $this->bottleneckAnalyzer->analyze();
        $scored = collect($bottlenecks)->map(function ($b) {
            $score = $this->scorer->score([
                'expected_improvement_pct' => 30,
                'confidence' => 0.7,
                'risk_tier' => 2,
                'complexity' => 2,
                'action_type' => 'analyze',
            ]);

            return array_merge($b, ['optimization_score' => $score]);
        })->sortByDesc(fn ($b) => $b['optimization_score']['score'])->values()->all();

        $this->writeReport('BOTTLENECK-REPORT.md', $this->formatBottleneckReport($scored));

        return [
            'mode' => OptimizationMode::Recommend->value,
            'level' => 1,
            'bottlenecks' => $scored,
            'count' => count($scored),
            'message' => 'Recommendations generated — awaiting approval for non-autonomous actions',
        ];
    }

    /**
     * Level 2 — Autonomous: ONE low-risk optimization via isolated runner.
     *
     * @return array<string, mixed>
     */
    public function runAutonomous(): array
    {
        if ($this->mode() !== OptimizationMode::Autonomous) {
            return [
                'executed' => false,
                'reason' => 'Autonomous mode disabled. Set OPTIMIZATION_MODE=autonomous to enable Level 2.',
            ];
        }

        $this->guardian->runPerformanceCycle();

        $pending = Recommendation::query()
            ->where('status', 'pending')
            ->where('risk_tier', '<=', config('optimization.scoring.max_risk_tier_autonomous', 1))
            ->orderByDesc('confidence')
            ->first();

        if ($pending === null) {
            return [
                'executed' => false,
                'reason' => 'No eligible pending recommendations for autonomous execution',
            ];
        }

        $baseline = $this->baselineService->latest();
        $baselineMetrics = [
            'p95_latency_ms' => 0,
            'db_queries_per_request' => 0,
        ];

        if ($baseline !== null) {
            $queryBaselines = $baseline->query_baselines ?? [];
            $p95Values = collect($queryBaselines)->pluck('p95_ms')->filter();
            $baselineMetrics['p95_latency_ms'] = $p95Values->avg() ?? 0;
        }

        return $this->runner->run($pending, $baselineMetrics);
    }

    /**
     * @param  list<array<string, mixed>>  $bottlenecks
     */
    private function formatBottleneckReport(array $bottlenecks): string
    {
        $lines = [
            '# Bottleneck Report',
            '',
            'Generated: '.now()->toIso8601String(),
            'Mode: '.config('optimization.mode'),
            '',
            '## Detected Bottlenecks',
            '',
        ];

        if ($bottlenecks === []) {
            $lines[] = '_No significant bottlenecks detected in current window._';
        }

        foreach ($bottlenecks as $i => $b) {
            $n = $i + 1;
            $lines[] = "### {$n}. {$b['component']}";
            $lines[] = "- **Domain:** {$b['domain']}";
            $lines[] = "- **Priority:** {$b['priority']}";
            $lines[] = '- **Evidence:** `'.json_encode($b['evidence']).'`';
            $lines[] = '- **Score:** '.($b['optimization_score']['score'] ?? 'N/A');
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function writeReport(string $filename, string $content): void
    {
        $path = config('optimization.reports_path');
        File::ensureDirectoryExists($path);
        File::put("{$path}/{$filename}", $content);
    }
}
