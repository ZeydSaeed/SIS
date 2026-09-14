<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use App\Domain\Promotion\ValueObjects\PromotionStatus;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhasePtPromotionRecordShowHttpApiPostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function manager_can_show_promotion_record(): void
    {
        $schoolId = $this->createSchool('SCH-PT-U06', 'PT Show Record');
        $yearId = $this->createAcademicYear('AY-PT-U06');
        $this->actingAsPromotionManagerForSchool($schoolId);

        $fromGrade = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GF-U06',
            'name' => 'From U06',
            'level_order' => 10,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $toGrade = (int) DB::table(SchemaHelper::qualified('academic', 'grade_levels'))->insertGetId([
            'code' => 'GT-U06',
            'name' => 'To U06',
            'level_order' => 11,
            'education_stage' => 1,
            'status' => 1,
        ]);
        $classId = (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $yearId,
            'grade_level_id' => $fromGrade,
            'code' => 'C-U06',
            'name' => 'Class U06',
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
            'student_code' => 'ST-U06',
            'first_name' => 'P',
            'last_name' => 'R',
            'full_name' => 'P R',
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
            'enrollment_number' => 'EN-U06',
            'status' => 1,
            'effective_from' => '2026-09-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recordId = (int) $this->postJson('/api/v1/promotion/records', [
            'enrollment_id' => $enrollmentId,
            'academic_year_id' => $yearId,
            'to_grade_level_id' => $toGrade,
            'promotion_status' => PromotionStatus::Promoted,
            'gpa_at_promotion' => '3.25',
            'notes' => 'Show test',
        ], ['X-Idempotency-Key' => 'pt-u06-rec'])->json('data.record_id');

        $this->getJson('/api/v1/promotion/records/'.$recordId)
            ->assertOk()
            ->assertJsonPath('data.id', $recordId)
            ->assertJsonPath('data.enrollment_id', $enrollmentId)
            ->assertJsonPath('data.from_grade_level_id', $fromGrade)
            ->assertJsonPath('data.to_grade_level_id', $toGrade)
            ->assertJsonPath('data.promotion_status', PromotionStatus::Promoted)
            ->assertJsonPath('data.gpa_at_promotion', '3.25');
    }
}
