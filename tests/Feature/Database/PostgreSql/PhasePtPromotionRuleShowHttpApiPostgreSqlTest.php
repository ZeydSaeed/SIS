<?php

namespace Tests\Feature\Database\PostgreSql;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhasePtPromotionRuleShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_promotion_rule(): void
    {
        $schoolId = $this->createSchool('SCH-PT-U05', 'PT Show Rule');
        $this->actingAsPromotionManagerForSchool($schoolId);

        $fromGrade = (int) DB::table(\App\Database\SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GF-U05',
            'name' => 'From U05',
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $toGrade = (int) DB::table(\App\Database\SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GT-U05',
            'name' => 'To U05',
            'level_order' => 11,
            'education_stage' => 1,
            'status' => 1,
        ]);

        $ruleId = (int) $this->postJson('/api/v1/promotion/rules', [
            'from_grade_level_id' => $fromGrade,
            'to_grade_level_id' => $toGrade,
            'min_gpa' => '2.75',
            'min_pass_subjects' => 6,
            'max_failed_subjects' => 1,
        ], ['X-Idempotency-Key' => 'pt-show-u05'])->json('data.rule_id');

        $this->getJson('/api/v1/promotion/rules/'.$ruleId)
            ->assertOk()
            ->assertJsonPath('data.id', $ruleId)
            ->assertJsonPath('data.from_grade_level_id', $fromGrade)
            ->assertJsonPath('data.to_grade_level_id', $toGrade)
            ->assertJsonPath('data.min_gpa', '2.75')
            ->assertJsonPath('data.min_pass_subjects', 6)
            ->assertJsonPath('data.is_active', true);
    }
}
