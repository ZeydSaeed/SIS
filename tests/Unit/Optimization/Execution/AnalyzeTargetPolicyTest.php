<?php

namespace Tests\Unit\Optimization\Execution;

use App\Intelligence\Models\Recommendation;
use App\Intelligence\Optimization\SafeAutoExecutor;
use App\Optimization\Execution\AnalyzeTargetPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyzeTargetPolicyTest extends TestCase
{
    use RefreshDatabase;

    private AnalyzeTargetPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new AnalyzeTargetPolicy;
    }

    #[Test]
    public function allowlist_missing_or_empty_blocks(): void
    {
        config(['optimization.analyze.allowed_targets' => []]);
        $this->assertFalse($this->policy->isAllowlisted('intelligence', 'optimization_validation_target'));

        config(['optimization.analyze.allowed_targets' => null]);
        $this->assertFalse($this->policy->isAllowlisted('intelligence', 'students'));
    }

    #[Test]
    public function approved_target_is_allowlisted_at_policy_layer(): void
    {
        config(['optimization.analyze.allowed_targets' => ['intelligence.optimization_validation_target']]);
        $this->assertTrue($this->policy->isAllowlisted('intelligence', 'optimization_validation_target'));
    }

    #[Test]
    public function unlisted_target_is_blocked(): void
    {
        config(['optimization.analyze.allowed_targets' => ['public.students']]);
        $this->assertFalse($this->policy->isAllowlisted('intelligence', 'optimization_validation_target'));
    }

    #[Test]
    public function production_default_mode_is_observe_and_blocks_autonomous_path(): void
    {
        $defaults = require config_path('optimization.php');
        $this->assertSame('observe', $defaults['mode'] ?? 'observe');

        config(['optimization.mode' => 'observe']);
        $engine = app(\App\Optimization\OptimizationEngine::class);
        $this->assertSame(
            \App\Optimization\Enums\OptimizationMode::Observe,
            $engine->mode(),
        );
    }

    #[Test]
    #[DataProvider('maliciousTargetProvider')]
    public function malicious_targets_are_rejected(string $schema, string $table): void
    {
        config(['optimization.analyze.allowed_targets' => ['intelligence.optimization_validation_target']]);

        $this->assertNull($this->policy->resolve($schema, $table));
        $this->assertNull($this->policy->toAnalyzeSql($schema, $table));
    }

    /** @return list<array{0: string, 1: string}> */
    public static function maliciousTargetProvider(): array
    {
        return [
            ['intelligence;drop', 'students'],
            ['intelligence', 'students;drop table users'],
            ['intelligence', 'students--comment'],
            ['intelligence', "students' OR '1'='1"],
            ['', 'students'],
            ['intelligence', ''],
            ['intelligence', 'valid_table;select pg_sleep(10)'],
            ['../../public', 'students'],
        ];
    }

    #[Test]
    public function valid_normalized_target_produces_quoted_sql(): void
    {
        config(['optimization.analyze.allowed_targets' => ['intelligence.optimization_validation_target']]);

        $sql = $this->policy->toAnalyzeSql('Intelligence', 'Optimization_Validation_Target');
        $this->assertSame('"intelligence"."optimization_validation_target"', $sql);
    }

    #[Test]
    public function learning_cannot_add_target_to_allowlist(): void
    {
        config([
            'optimization.analyze.allowed_targets' => [],
            'optimization.environments.testing.max_risk_tier_autonomous' => 1,
        ]);

        $ranker = app(\App\Optimization\SelfHealing\RecommendationRanker::class);
        $this->assertSame(1, $ranker->maxAutonomousRiskTier());

        $recommendation = new Recommendation([
            'rule_id' => 'rule_analyze_table',
            'confidence' => 0.99,
            'risk_tier' => 1,
            'action_type' => 'analyze',
            'schema_name' => 'intelligence',
            'table_name' => 'optimization_validation_target',
        ]);

        $ranker->rank(collect([$recommendation]));

        $this->assertFalse($this->policy->isAllowlisted('intelligence', 'optimization_validation_target'));
        $this->assertSame([], config('optimization.analyze.allowed_targets'));
    }
}
