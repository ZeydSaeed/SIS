<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhasePtPromotionRuleDeactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_deactivate_promotion_rule(): void
    {
        $schoolId = $this->createSchool('SCH-PT-D1', 'Promotion Deactivate');
        $fromGrade = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GF-PTD1',
            'name' => 'From',
            'level_order' => 20,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $toGrade = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GT-PTD1',
            'name' => 'To',
            'level_order' => 21,
            'education_stage' => 1,
            'status' => 1,
        ]);

        $this->actingAsPromotionManagerForSchool($schoolId);

        $ruleId = (int) $this->postJson('/api/v1/promotion/rules', [
            'from_grade_level_id' => $fromGrade,
            'to_grade_level_id' => $toGrade,
            'min_gpa' => '2.0',
        ], ['X-Idempotency-Key' => 'pt-d-create'])->assertCreated()->json('data.rule_id');

        $this->postJson('/api/v1/promotion/rules/'.$ruleId.'/deactivate', [], [
            'X-Idempotency-Key' => 'pt-d-off',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas(SchemaHelper::qualified('promotion', 'rules'), [
            'id' => $ruleId,
            'is_active' => false,
        ]);
    }
}
