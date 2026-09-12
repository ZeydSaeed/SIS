<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Promotion\ValueObjects\PromotionStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhasePtPromotionHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    /**
     * @return array{school_id:int,year_id:int,from_grade:int,to_grade:int,enrollment_id:int}
     */
    private function seedEnrollmentContext(string $suffix): array
    {
        $schoolId = $this->createSchool('SCH-PT-'.$suffix, 'Promotion '.$suffix);
        $yearId = $this->createAcademicYear('AY-PT-'.$suffix);

        $fromGrade = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GF-'.$suffix,
            'name' => 'From '.$suffix,
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $toGrade = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GT-'.$suffix,
            'name' => 'To '.$suffix,
            'level_order' => 11,
            'education_stage' => 1,
            'status' => 1,
        ]);

        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'grade_level_id' => $fromGrade,
            'code' => 'C-'.$suffix,
            'name' => 'Class '.$suffix,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $sectionId = (int) DB::table(SchemaHelper::qualified('enrollment', 'sections'))->insertGetId([
            'class_id' => $classId,
            'code' => 'S1',
            'name' => 'Section 1',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $studentId = (int) DB::table(SchemaHelper::qualified('students', 'students'))->insertGetId([
            'school_id' => $schoolId,
            'student_code' => 'ST-'.$suffix,
            'first_name' => 'P',
            'last_name' => 'T',
            'full_name' => 'P T',
            'gender' => 1,
            'birth_date' => '2012-01-01',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $enrollmentId = (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->insertGetId([
            'student_id' => $studentId,
            'academic_year_id' => $yearId,
            'school_id' => $schoolId,
            'class_id' => $classId,
            'section_id' => $sectionId,
            'enrollment_number' => 'EN-'.$suffix,
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'from_grade' => $fromGrade,
            'to_grade' => $toGrade,
            'enrollment_id' => $enrollmentId,
        ];
    }

    #[Test]
    public function promotion_permissions_are_registered(): void
    {
        $this->assertArrayHasKey(\App\Security\Authorization\Permission::PROMOTION_VIEW, config('security.permissions'));
        $this->assertArrayHasKey(\App\Security\Authorization\Permission::PROMOTION_MANAGE, config('security.permissions'));
        $this->assertContains(
            \App\Security\Authorization\Permission::PROMOTION_MANAGE,
            config('security.roles.promotion_manager'),
        );
    }

    #[Test]
    public function manager_can_create_rule_and_record_decision(): void
    {
        $ctx = $this->seedEnrollmentContext('U2A');
        $this->actingAsPromotionManagerForSchool($ctx['school_id']);

        $rule = $this->postJson('/api/v1/promotion/rules', [
            'from_grade_level_id' => $ctx['from_grade'],
            'to_grade_level_id' => $ctx['to_grade'],
            'min_gpa' => '2.50',
            'min_pass_subjects' => 5,
            'max_failed_subjects' => 2,
        ], ['X-Idempotency-Key' => 'pt-rule-1'])
            ->assertCreated()
            ->assertJsonPath('data.from_idempotency', false);

        $ruleId = (int) $rule->json('data.rule_id');

        $this->postJson('/api/v1/promotion/rules', [
            'from_grade_level_id' => $ctx['from_grade'],
            'to_grade_level_id' => $ctx['to_grade'],
            'min_gpa' => '2.50',
        ], ['X-Idempotency-Key' => 'pt-rule-1'])
            ->assertOk()
            ->assertJsonPath('data.rule_id', $ruleId)
            ->assertJsonPath('data.from_idempotency', true);

        $this->getJson('/api/v1/promotion/rules')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ruleId)
            ->assertJsonPath('data.0.from_grade_level_id', $ctx['from_grade']);

        $record = $this->postJson('/api/v1/promotion/records', [
            'enrollment_id' => $ctx['enrollment_id'],
            'academic_year_id' => $ctx['year_id'],
            'to_grade_level_id' => $ctx['to_grade'],
            'promotion_status' => PromotionStatus::Promoted,
            'gpa_at_promotion' => '3.10',
            'notes' => 'Manual decision',
        ], ['X-Idempotency-Key' => 'pt-rec-1'])
            ->assertCreated();

        $recordId = (int) $record->json('data.record_id');

        $this->getJson('/api/v1/promotion/records?academic_year_id='.$ctx['year_id'])
            ->assertOk()
            ->assertJsonPath('data.0.id', $recordId)
            ->assertJsonPath('data.0.promotion_status', PromotionStatus::Promoted)
            ->assertJsonPath('data.0.from_grade_level_id', $ctx['from_grade']);

        $this->assertDatabaseHas(SchemaHelper::qualified('enrollment', 'enrollments'), [
            'id' => $ctx['enrollment_id'],
            'status' => 1,
            'effective_to' => null,
        ]);

        $this->postJson('/api/v1/promotion/records', [
            'enrollment_id' => $ctx['enrollment_id'],
            'academic_year_id' => $ctx['year_id'],
            'to_grade_level_id' => $ctx['to_grade'],
            'promotion_status' => PromotionStatus::Promoted,
        ], ['X-Idempotency-Key' => 'pt-rec-dup'])
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'promotion.decision_exists');
    }

    #[Test]
    public function viewer_can_list_but_cannot_mutate(): void
    {
        $ctx = $this->seedEnrollmentContext('U2B');
        $this->actingAsPromotionViewerForSchool($ctx['school_id']);

        $this->postJson('/api/v1/promotion/rules', [
            'from_grade_level_id' => $ctx['from_grade'],
            'to_grade_level_id' => $ctx['to_grade'],
        ], ['X-Idempotency-Key' => 'pt-deny'])
            ->assertForbidden();

        $this->getJson('/api/v1/promotion/rules')->assertOk();
        $this->getJson('/api/v1/promotion/records?academic_year_id='.$ctx['year_id'])->assertOk();
    }
}
