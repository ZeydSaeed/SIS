<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhasePtPromotionRuleReactivateHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_reactivate_promotion_rule(): void
    {
        $schoolId = $this->createSchool('SCH-PT-R1', 'Promotion Reactivate');
        $fromGrade = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GF-PTR1',
            'name' => 'From',
            'level_order' => 22,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $toGrade = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GT-PTR1',
            'name' => 'To',
            'level_order' => 23,
            'education_stage' => 1,
            'status' => 1,
        ]);

        $this->actingAsPromotionManagerForSchool($schoolId);

        $ruleId = (int) $this->postJson('/api/v1/promotion/rules', [
            'from_grade_level_id' => $fromGrade,
            'to_grade_level_id' => $toGrade,
            'min_gpa' => '2.0',
        ], ['X-Idempotency-Key' => 'pt-r-create'])->assertCreated()->json('data.rule_id');

        $this->postJson('/api/v1/promotion/rules/'.$ruleId.'/deactivate', [], [
            'X-Idempotency-Key' => 'pt-r-off',
        ])->assertOk();

        $this->postJson('/api/v1/promotion/rules/'.$ruleId.'/reactivate', [], [
            'X-Idempotency-Key' => 'pt-r-on',
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas(SchemaHelper::qualified('promotion', 'rules'), [
            'id' => $ruleId,
            'is_active' => true,
        ]);
    }
}
