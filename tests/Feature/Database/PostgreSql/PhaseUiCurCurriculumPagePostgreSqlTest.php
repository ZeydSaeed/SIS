<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

final class PhaseUiCurCurriculumPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function guest_is_redirected_from_curriculum_page(): void
    {
        $this->get('/curriculum')->assertRedirect('/login');
    }

    #[Test]
    public function curriculum_manager_can_view_curriculum_page(): void
    {
        $schoolId = $this->createSchool('SCH-CUR-UI', 'Curriculum UI');
        $yearId = $this->createAcademicYear('AY-CUR-UI');
        $this->actingAsCurriculumManagerWeb(schoolId: $schoolId);

        $this->get('/curriculum?academic_year_id='.$yearId)
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('curriculum/index')
                ->has('curricula.data')
                ->has('curricula.pagination')
                ->has('subjects.data')
                ->has('subjects.pagination')
                ->has('linkedSubjects')
                ->has('filters')
                ->has('filterOptions')
                ->has('authorization')
                ->where('authorization.canManage', true));
    }

    #[Test]
    public function curriculum_manager_can_create_and_open_show_with_catalog_subjects(): void
    {
        $schoolId = $this->createSchool('SCH-CUR-CR', 'Curriculum Create');
        $yearId = $this->createAcademicYear('AY-CUR-CR');
        $gradeId = $this->createGradeLevel('G-CUR-CR');

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $subjectId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'SUB-CUR-CR',
            'name' => 'التربية الاسلامية',
            'name_en' => null,
            'subject_type' => 1,
            'credit_hours' => 2,
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $specId = (int) DB::table(SchemaHelper::qualified('vocational', 'specializations'))->insertGetId([
            'school_id' => $schoolId,
            'code' => 'SPC-CUR-CR',
            'name' => 'كهرباء',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table(SchemaHelper::qualified('vocational', 'specialization_subjects'))->insert([
            'specialization_id' => $specId,
            'subject_id' => $subjectId,
            'is_required' => true,
            'credit_hours' => 2,
            'status' => 1,
        ]);

        $this->actingAsCurriculumManagerWeb(schoolId: $schoolId);

        $response = $this->withHeader('X-Idempotency-Key', 'web-cur-create-1')
            ->post('/curriculum/curricula', [
                'academic_year_id' => $yearId,
                'grade_level_id' => $gradeId,
                'name' => 'الصناعي — كهرباء — الأول',
                'specialization_id' => $specId,
            ]);

        $curriculumId = (int) DB::table(SchemaHelper::qualified('curriculum', 'curricula'))
            ->where('school_id', $schoolId)
            ->where('name', 'الصناعي — كهرباء — الأول')
            ->value('id');

        $this->assertGreaterThan(0, $curriculumId);
        $response->assertRedirect(route('curriculum.show', [
            'curriculum' => $curriculumId,
            'academic_year_id' => $yearId,
        ], absolute: false));

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'curriculum_subjects'), [
            'curriculum_id' => $curriculumId,
            'subject_id' => $subjectId,
            'weekly_hours' => 2,
            'is_required' => true,
            'status' => 1,
        ]);

        $this->get('/curriculum/curricula/'.$curriculumId)
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('curriculum/show')
                ->where('curriculum.id', $curriculumId)
                ->has('linkedSubjects')
                ->has('prerequisites')
                ->has('enrollments.data')
                ->has('authorization'));
    }

    #[Test]
    public function curriculum_manager_can_add_prerequisite_via_web(): void
    {
        $schoolId = $this->createSchool('SCH-CUR-PR', 'Curriculum Prereq');
        $this->actingAsCurriculumManagerWeb(schoolId: $schoolId);

        $mathId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'SUB-PR-M',
            'name' => 'الرياضيات',
            'name_en' => null,
            'subject_type' => 1,
            'credit_hours' => 2,
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $algId = (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => 'SUB-PR-A',
            'name' => 'الجبر',
            'name_en' => null,
            'subject_type' => 1,
            'credit_hours' => 2,
            'max_grade' => 100,
            'pass_grade' => 50,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeader('X-Idempotency-Key', 'web-prereq-1')
            ->post('/curriculum/subjects/'.$algId.'/prerequisites', [
                'prerequisite_subject_id' => $mathId,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas(SchemaHelper::qualified('curriculum', 'prerequisites'), [
            'subject_id' => $algId,
            'prerequisite_subject_id' => $mathId,
            'status' => 1,
        ]);
    }
}
