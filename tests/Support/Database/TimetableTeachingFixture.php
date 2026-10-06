<?php

namespace Tests\Support\Database;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

/**
 * Timetable writes require the teacher to teach the subject (teachers.teacher_subjects).
 * Timetable fixtures call this after seeding so every teacher of the school/year teaches
 * every subject — tests that need a missing assignment delete nothing and seed their own.
 */
final class TimetableTeachingFixture
{
    public static function assignSubjectsToTeachers(int $schoolId, int $academicYearId): void
    {
        $teacherIds = DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->pluck('teacher_id');
        $subjectIds = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->pluck('id');

        foreach ($teacherIds as $teacherId) {
            foreach ($subjectIds as $subjectId) {
                DB::table(SchemaHelper::qualified('teachers', 'teacher_subjects'))->insertOrIgnore([
                    'teacher_id' => (int) $teacherId,
                    'subject_id' => (int) $subjectId,
                    'academic_year_id' => $academicYearId,
                    'school_id' => $schoolId,
                    'created_at' => now(),
                ]);
            }
        }
    }
}
