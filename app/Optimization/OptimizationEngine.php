<?php

namespace App\Optimization;

use App\Intelligence\Guardian\DatabaseGuardian;
use App\Intelligence\Models\Recommendation;
use App\Optimization\Analysis\BottleneckAnalyzer;
use App\Optimization\Analysis\OptimizationScorer;
use App\Optimization\Baseline\BaselineSnapshotService;
use App\Optimization\Contracts\IncidentReport;
use App\Optimization\Enums\OptimizationMode;
use App\Optimization\Execution\IsolatedOptimizationRunner;
use App\Optimization\Memory\OptimizationHistoryRecorder;
use App\Optimization\SelfHealing\IncidentRecommendationResolver;
use App\Optimization\SelfHealing\MetricSnapshotCapturer;
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
        private readonly IncidentRecommendationResolver $incidentResolver,
        private readonly MetricSnapshotCapturer $metricCapturer,
    ) {}

    public function mode(): OptimizationMode
    {
        return OptimizationMode::tryFrom(config('optimization.mode', 'observe'))
            ?? OptimizationMode::Observe;
    }

    /**
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
     * Execute optimization for a specific incident — RCA drives recommendation selection.
     *
     * @return array<string, mixed>
     */
    public function runForIncident(IncidentReport $incident, ?string $checkpointId = null): array
    {
        if ($this->mode() !== OptimizationMode::Autonomous) {
            return [
                'executed' => false,
                'reason' => 'Autonomous mode disabled. Set OPTIMIZATION_MODE=autonomous to enable Level 2.',
            ];
        }

        if ($incident->candidateActions === []) {
            return [
                'executed' => false,
                'reason' => 'No autonomous actions available for incident root cause',
            ];
        }

        $resolved = $this->incidentResolver->resolve($incident);
        if (! ($resolved['matched'] ?? false) || $resolved['recommendation'] === null) {
            return [
                'executed' => false,
                'reason' => $resolved['reason'] ?? 'No matching recommendation for incident',
                'incident_id' => $incident->incidentId,
                'target' => $incident->target,
            ];
        }

        /** @var Recommendation $recommendation */
        $recommendation = $resolved['recommendation'];
        $this->incidentResolver->assertMatches($incident, $recommendation);

        $beforeMetrics = $this->metricCapturer->capture();

        return $this->runner->run($recommendation, $beforeMetrics, $checkpointId);
    }

    /**
     * @deprecated Use runForIncident() from SelfHealingPerformanceEngine
     *
     * @return array<string, mixed>
     */
    public function runAutonomous(): array
    {
        return [
            'executed' => false,
            'reason' => 'Direct autonomous execution disabled — use incident-driven runForIncident()',
        ];
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
