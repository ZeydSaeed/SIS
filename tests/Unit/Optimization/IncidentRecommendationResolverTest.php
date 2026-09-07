<?php

namespace Tests\Unit\Optimization;

use App\Intelligence\Guardian\DatabaseGuardian;
use App\Intelligence\Models\Recommendation;
use App\Optimization\Contracts\IncidentReport;
use App\Optimization\SelfHealing\IncidentRecommendationResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IncidentRecommendationResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'optimization.environments.testing.max_risk_tier_autonomous' => 1,
            'optimization.autonomous_actions' => ['analyze'],
        ]);
    }

    #[Test]
    public function s3_it_matches_database_target_recommendation_for_incident(): void
    {
        $this->mock(DatabaseGuardian::class, function ($mock) {
            $mock->shouldReceive('runPerformanceCycle')->once();
        });

        Recommendation::query()->create([
            'recommendation_code' => 'REC-TEST-001',
            'rule_id' => 'rule_analyze_table',
            'risk_tier' => 1,
            'action_type' => 'analyze',
            'schema_name' => 'public',
            'table_name' => 'students',
            'what' => 'ANALYZE students',
            'why' => 'Stale statistics',
            'confidence' => 0.85,
            'status' => 'pending',
            'evidence' => ['query_fingerprint' => 'fp_students_001'],
        ]);

        Recommendation::query()->create([
            'recommendation_code' => 'REC-TEST-002',
            'rule_id' => 'rule_analyze_table',
            'risk_tier' => 1,
            'action_type' => 'analyze',
            'schema_name' => 'public',
            'table_name' => 'attendance_records',
            'what' => 'ANALYZE attendance_records',
            'why' => 'Different table',
            'confidence' => 0.99,
            'status' => 'pending',
        ]);

        $incident = new IncidentReport(
            incidentId: 'INC-TEST-001',
            timestamp: now()->toIso8601String(),
            primaryComponent: 'database',
            affectedComponent: 'public.students',
            target: 'public.students',
            rootCause: 'stale_planner_statistics',
            confidence: 0.87,
            evidence: [],
            supportingMetrics: [],
            candidateActions: ['analyze'],
            risk: 'low',
            severity: 'P1',
            schemaName: 'public',
            tableName: 'students',
            queryFingerprint: 'fp_students_001',
        );

        $resolver = app(IncidentRecommendationResolver::class);
        $resolved = $resolver->resolve($incident);

        $this->assertTrue($resolved['matched']);
        $this->assertSame('students', $resolved['recommendation']->table_name);
        $this->assertSame('INC-TEST-001', $resolved['recommendation']->correlation_id);
    }

    #[Test]
    public function s4_it_rejects_when_no_recommendation_matches_incident_target(): void
    {
        $this->mock(DatabaseGuardian::class, function ($mock) {
            $mock->shouldReceive('runPerformanceCycle')->once();
        });

        Recommendation::query()->create([
            'recommendation_code' => 'REC-TEST-WRONG',
            'rule_id' => 'rule_analyze_table',
            'risk_tier' => 1,
            'action_type' => 'analyze',
            'schema_name' => 'public',
            'table_name' => 'unrelated_table',
            'what' => 'ANALYZE unrelated_table',
            'why' => 'Wrong target',
            'confidence' => 0.99,
            'status' => 'pending',
        ]);

        $incident = new IncidentReport(
            incidentId: 'INC-TEST-002',
            timestamp: now()->toIso8601String(),
            primaryComponent: 'database',
            affectedComponent: 'public.students',
            target: 'public.students',
            rootCause: 'stale_planner_statistics',
            confidence: 0.87,
            evidence: [],
            supportingMetrics: [],
            candidateActions: ['analyze'],
            risk: 'low',
            severity: 'P1',
            schemaName: 'public',
            tableName: 'students',
        );

        $resolver = app(IncidentRecommendationResolver::class);
        $resolved = $resolver->resolve($incident);

        $this->assertFalse($resolved['matched']);
        $this->assertNull($resolved['recommendation']);
    }

    #[Test]
    public function s4_assert_matches_throws_on_incident_mismatch(): void
    {
        $incident = new IncidentReport(
            incidentId: 'INC-TEST-003',
            timestamp: now()->toIso8601String(),
            primaryComponent: 'database',
            affectedComponent: 'public.students',
            target: 'public.students',
            rootCause: 'stale_planner_statistics',
            confidence: 0.87,
            evidence: [],
            supportingMetrics: [],
            candidateActions: ['analyze'],
            risk: 'low',
            severity: 'P1',
            tableName: 'students',
        );

        $recommendation = new Recommendation([
            'recommendation_code' => 'REC-MISMATCH',
            'action_type' => 'analyze',
            'table_name' => 'other_table',
            'correlation_id' => 'INC-OTHER',
        ]);

        $resolver = app(IncidentRecommendationResolver::class);

        $this->expectException(\RuntimeException::class);
        $resolver->assertMatches($incident, $recommendation);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
