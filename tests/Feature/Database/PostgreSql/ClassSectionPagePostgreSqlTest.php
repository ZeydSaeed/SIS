<?php

namespace Tests\Feature\Database\PostgreSql;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\InteractsWithSecurity;
use Tests\Support\Database\PostgreSqlIntegrationTestCase;

/** «الصفوف والشعب» page — /organization/classes-sections. */
final class ClassSectionPagePostgreSqlTest extends PostgreSqlIntegrationTestCase
{
    use InteractsWithSecurity;

    #[Test]
    public function guest_is_redirected(): void
    {
        $this->get('/organization/classes-sections')->assertRedirect('/login');
    }

    #[Test]
    public function manager_creates_a_class_and_a_section_with_a_homeroom_teacher(): void
    {
        [$schoolId, $yearId, $gradeId] = $this->seedSchool('CS-A');
        $this->actingAsEnrollmentManagerForSchool($schoolId);
        $teacherId = $this->insertTeacher($schoolId, $yearId, 'HR-A');

        $this->from('/organization/classes-sections')
            ->withHeader('X-Idempotency-Key', 'cs-a-class')
            ->post('/organization/classes', [
                'academic_year_id' => $yearId,
                'grade_level_id' => $gradeId,
                'name' => 'الأول أ',
                'capacity' => 60,
            ])
            ->assertRedirect('/organization/classes-sections')
            ->assertSessionHas('success', 'flash.structure.classCreated');

        $classId = (int) DB::table('enrollment.classes')->where('school_id', $schoolId)->where('name', 'الأول أ')->value('id');
        $this->assertSame('CLS-1', DB::table('enrollment.classes')->where('id', $classId)->value('code'));

        $this->from('/organization/classes-sections')
            ->withHeader('X-Idempotency-Key', 'cs-a-section')
            ->post('/organization/sections', [
                'class_id' => $classId,
                'name' => 'شعبة 1',
                'capacity' => 30,
                'homeroom_teacher_id' => $teacherId,
            ])
            ->assertSessionHas('success', 'flash.structure.sectionCreated');

        $section = DB::table('enrollment.sections')->where('class_id', $classId)->first();
        $this->assertNotNull($section);
        $this->assertSame('SEC-1', $section->code);
        $this->assertSame($teacherId, (int) $section->homeroom_teacher_id);

        $this->get('/organization/classes-sections?academic_year_id='.$yearId)
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->component('organization/classes-sections')
                ->where('authorization.can_manage', true)
                ->has('classes', 1)
                ->where('classes.0.code', 'CLS-1')
                ->where('classes.0.sections.0.homeroom_teacher_id', $teacherId)
                ->where('classes.0.sections.0.enrolled', 0)
                ->where('teachers.0.id', $teacherId)
                ->etc());
    }

    #[Test]
    public function duplicate_names_and_foreign_homeroom_teachers_are_rejected(): void
    {
        [$schoolId, $yearId, $gradeId] = $this->seedSchool('CS-B');
        $this->actingAsEnrollmentManagerForSchool($schoolId);
        $classId = $this->insertClass($schoolId, $yearId, $gradeId, 'CLS-1', 'الثاني');

        $this->from('/organization/classes-sections')
            ->withHeader('X-Idempotency-Key', 'cs-b-dup')
            ->post('/organization/classes', ['academic_year_id' => $yearId, 'grade_level_id' => $gradeId, 'name' => 'الثاني'])
            ->assertSessionHasErrors(['class' => 'enrollment.class_name_taken']);

        // A teacher of another school is not a valid homeroom teacher here.
        [$otherSchool] = $this->seedSchool('CS-B2', $yearId, $gradeId);
        $foreignTeacher = $this->insertTeacher($otherSchool, $yearId, 'HR-B');
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $this->from('/organization/classes-sections')
            ->withHeader('X-Idempotency-Key', 'cs-b-homeroom')
            ->post('/organization/sections', ['class_id' => $classId, 'name' => 'أ', 'homeroom_teacher_id' => $foreignTeacher])
            ->assertSessionHasErrors(['section' => 'enrollment.section_homeroom_invalid']);
    }

    #[Test]
    public function enrolled_classes_keep_their_grade_and_capacity_cannot_drop_below_enrolled(): void
    {
        [$schoolId, $yearId, $gradeId] = $this->seedSchool('CS-C');
        $this->actingAsEnrollmentManagerForSchool($schoolId);
        $otherGrade = (int) DB::table('academic.grade_levels')->insertGetId([
            'code' => 'G-CS-C2', 'name' => 'Other grade', 'level_order' => 12, 'education_stage' => 1, 'status' => 1,
        ]);
        $classId = $this->insertClass($schoolId, $yearId, $gradeId, 'CLS-1', 'الثالث');
        $sectionId = (int) DB::table('enrollment.sections')->insertGetId([
            'class_id' => $classId, 'code' => 'SEC-1', 'name' => 'أ', 'capacity' => 30, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['S1', 'S2'] as $tag) {
            $studentId = (int) DB::table('students.students')->insertGetId([
                'school_id' => $schoolId, 'student_code' => 'CS-C-'.$tag, 'first_name' => 'A', 'last_name' => $tag,
                'full_name' => 'A '.$tag, 'gender' => 1, 'birth_date' => '2010-01-01', 'status' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('enrollment.enrollments')->insert([
                'student_id' => $studentId, 'academic_year_id' => $yearId, 'school_id' => $schoolId,
                'class_id' => $classId, 'section_id' => $sectionId, 'status' => 1,
                'effective_from' => '2026-09-01', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->from('/organization/classes-sections')
            ->withHeader('X-Idempotency-Key', 'cs-c-grade')
            ->patch('/organization/classes/'.$classId, ['grade_level_id' => $otherGrade, 'name' => 'الثالث'])
            ->assertSessionHasErrors(['class' => 'enrollment.class_grade_level_locked']);

        $this->from('/organization/classes-sections')
            ->withHeader('X-Idempotency-Key', 'cs-c-capacity')
            ->patch('/organization/sections/'.$sectionId, ['name' => 'أ', 'capacity' => 1])
            ->assertSessionHasErrors(['section' => 'enrollment.section_capacity_below_enrolled']);

        $this->from('/organization/classes-sections')
            ->withHeader('X-Idempotency-Key', 'cs-c-ok')
            ->patch('/organization/sections/'.$sectionId, ['name' => 'أ', 'capacity' => 2])
            ->assertSessionHas('success', 'flash.structure.sectionUpdated');
    }

    #[Test]
    public function viewer_without_update_permission_cannot_create_classes(): void
    {
        [$schoolId, $yearId, $gradeId] = $this->seedSchool('CS-D');
        $this->actingAsTeachersViewerForSchool($schoolId);

        $this->withHeader('X-Idempotency-Key', 'cs-d-class')
            ->post('/organization/classes', ['academic_year_id' => $yearId, 'grade_level_id' => $gradeId, 'name' => 'X'])
            ->assertForbidden();
    }

    /**
     * @return array{0:int,1:int,2:int} school, year, grade level
     */
    private function seedSchool(string $tag, ?int $yearId = null, ?int $gradeId = null): array
    {
        $schoolId = $this->createSchool('SCH-'.$tag, 'School '.$tag);
        $yearId ??= $this->createAcademicYear('AY-'.$tag);
        $gradeId ??= (int) DB::table('academic.grade_levels')->insertGetId([
            'code' => 'G-'.$tag, 'name' => 'Grade '.$tag, 'level_order' => 10, 'education_stage' => 1, 'status' => 1,
        ]);

        return [$schoolId, $yearId, $gradeId];
    }

    private function insertClass(int $schoolId, int $yearId, int $gradeId, string $code, string $name): int
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        return (int) DB::table(SchemaHelper::qualified('enrollment', 'classes'))->insertGetId([
            'school_id' => $schoolId, 'academic_year_id' => $yearId, 'grade_level_id' => $gradeId,
            'code' => $code, 'name' => $name, 'capacity' => 50, 'status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function insertTeacher(int $schoolId, int $yearId, string $tag): int
    {
        $id = (int) DB::table(SchemaHelper::qualified('teachers', 'teachers'))->insertGetId([
            'employee_code' => 'T'.substr(uniqid($tag), -10), 'first_name' => 'T', 'last_name' => $tag,
            'full_name' => 'T '.$tag, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))->insert([
            'teacher_id' => $id, 'school_id' => $schoolId, 'academic_year_id' => $yearId,
            'is_primary' => true, 'created_at' => now(),
        ]);

        return $id;
    }
}
