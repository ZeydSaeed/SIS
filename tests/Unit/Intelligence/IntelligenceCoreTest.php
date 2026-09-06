<?php

namespace Tests\Unit\Intelligence;

use App\Intelligence\Enums\RiskTier;
use App\Intelligence\Governance\RiskPolicy;
use App\Intelligence\Support\ConditionEvaluator;
use Tests\TestCase;

class IntelligenceCoreTest extends TestCase
{
    public function test_condition_evaluator_matches_threshold_rules(): void
    {
        $evaluator = new ConditionEvaluator;

        $this->assertTrue($evaluator->matches(
            ['table_size_gb' => '> 10'],
            ['table_size_gb' => 12.5]
        ));

        $this->assertFalse($evaluator->matches(
            ['query_p95_ms' => '> 500'],
            ['query_p95_ms' => 120]
        ));
    }

    public function test_risk_policy_marks_forbidden_actions(): void
    {
        $policy = new RiskPolicy;

        $this->assertSame(RiskTier::Forbidden, $policy->tierForAction('drop_index'));
        $this->assertSame(RiskTier::SafeAuto, $policy->tierForAction('analyze'));
        $this->assertSame(RiskTier::HumanReview, $policy->tierForAction('index_add'));
    }

    public function test_risk_policy_allows_only_tier_one_auto_execution_by_default(): void
    {
        $policy = new RiskPolicy;

        $this->assertTrue($policy->canAutoExecute(RiskTier::SafeAuto));
        $this->assertFalse($policy->canAutoExecute(RiskTier::HumanReview));
    }
}
