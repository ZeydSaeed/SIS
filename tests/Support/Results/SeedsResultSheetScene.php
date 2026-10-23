<?php

namespace Tests\Support\Results;

use App\Database\SchemaHelper;
use App\Domain\Exams\ValueObjects\GradeStatus;
use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\Exams\SeedsApplicationExamGradeGraph;

/**
 * One school, one exam session of a term/subject and a class whose three seated students have a finalized grade (80), an
 * entered grade (60) and no grade — the scene of the term and annual / GPA result sheets.
 */
trait SeedsResultSheetScene
{
    use SeedsApplicationExamGradeGraph;

    /** @return array<string, mixed> */
    protected function scene(int $weight = 100): array
    {
        $school = $this->createSchool('SCH-TRS', 'Term results');
        $g = $this->seedExamGradeGraph($school, suffix: 'TRS');
        $this->markSessionInProgressForGradeEntry($g['session_id']);
        $typeId = (int) DB::table(SchemaHelper::qualified('exams', 'exams'))->where('id', $g['exam_id'])->value('exam_type_id');
        DB::table(SchemaHelper::qualified('exams', 'exam_types'))->where('id', $typeId)->update(['weight_percentage' => $weight]);
        $class = (int) $this->createClassForSchool($school, $g['year_id'])->id;
        $section = (int) $this->createSectionForClass($class)->id;

        $student = function (?string $score, int $status) use ($school, $g, $class, $section): int {
            $e = $this->createActiveEnrollmentForSchool($school, $g['year_id']);
            DB::table(SchemaHelper::qualified('enrollment', 'enrollments'))->where('id', $e->id)->update(['class_id' => $class, 'section_id' => $section]);
            $seat = (int) DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))->insertGetId([
                'exam_session_id' => $g['session_id'], 'school_id' => $school, 'enrollment_id' => $e->id, 'status' => 1, 'created_at' => now(),
            ]);
            if ($score !== null) {
                DB::table(SchemaHelper::qualified('exams', 'student_grades'))->insert([
                    'academic_year_id' => $g['year_id'], 'school_id' => $school, 'exam_enrollment_id' => $seat, 'exam_session_id' => $g['session_id'],
                    'enrollment_id' => $e->id, 'student_id' => $e->student_id, 'subject_id' => $g['subject_id'], 'score' => $score, 'max_score' => 100,
                    'is_absent' => false, 'status' => $status, 'is_current' => true, 'entered_at' => now(),
                    'finalized_at' => $status === GradeStatus::Finalized->value ? now() : null, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return (int) $e->id;
        };

        return $g + [
            'school' => $school, 'class' => $class, 'section' => $section,
            'final' => $student('80', GradeStatus::Finalized->value),
            'entered' => $student('60', GradeStatus::Entered->value),
            'ungraded' => $student(null, 0),
        ];
    }

    protected function as(string $role, int $school): User
    {
        $user = User::factory()->create();
        app(SecurityPermissionSeeder::class)->{'grant'.$role}($user, $school);
        $this->actingAs($user);
        $this->withSession(['current_school_id' => $school]);

        return $user;
    }
}
