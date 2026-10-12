<?php

namespace Tests\Support\Attendance;

use App\Models\User;
use Database\Seeders\SecurityPermissionSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * A real timetable lesson for the attendance workflow tests: a section with enrolled students, a subject, a
 * period (08:00–08:45 school time) and a working-grid schedule row on Thursday 2026-10-15, taught by a teacher
 * whose user account is linked (teachers.user_id) and who works in the school that year.
 *
 * Requires Tests\Concerns\InteractsWithSecurity.
 */
trait SeedsAttendanceLessonGraph
{
    protected const LESSON_DATE = '2026-10-15';

    /** 08:10 in Asia/Baghdad (UTC+3) — inside the lesson window. */
    protected const DURING_LESSON_UTC = '2026-10-15 05:10:00';

    /**
     * @return array{school_id: int, year_id: int, section_id: int, class_id: int, subject_id: int, period_id: int, schedule_id: int,
     *               teacher_id: int, teacher_user: User, enrollments: list<int>, students: list<int>}
     */
    protected function seedLessonGraph(int $schoolId, int $students = 2, ?int $yearId = null): array
    {
        $yearId ??= $this->createAcademicYear();
        // The request context resolves the current year: make this the only one (other suites may leave another).
        DB::table('academic.academic_years')->where('id', '<>', $yearId)->update(['is_current' => false]);
        DB::table('academic.academic_years')->where('id', $yearId)->update(['is_current' => true]);
        $class = $this->createClassForSchool($schoolId, $yearId);
        $section = $this->createSectionForClass((int) $class->id);

        $enrollments = [];
        $studentIds = [];
        for ($i = 1; $i <= $students; $i++) {
            $student = $this->createStudentForSchool($schoolId, ['full_name' => "طالب {$i}", 'first_name' => "طالب{$i}"]);
            $studentIds[] = (int) $student->id;
            $enrollments[] = (int) DB::table('enrollment.enrollments')->insertGetId([
                'student_id' => $student->id,
                'academic_year_id' => $yearId,
                'school_id' => $schoolId,
                'class_id' => $class->id,
                'section_id' => $section->id,
                'status' => 1,
                'effective_from' => '2026-09-01',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $subjectId = (int) DB::table('curriculum.subjects')->insertGetId([
            'code' => 'ATT'.substr(uniqid(), -5), 'name' => 'رياضيات', 'subject_type' => 1, 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $teacher = $this->seedTeacher($schoolId, $yearId);
        $periodId = (int) DB::table('timetable.periods')->insertGetId([
            'school_id' => $schoolId, 'period_number' => random_int(100, 30000), 'start_time' => '08:00:00', 'end_time' => '08:45:00', 'period_type' => 1,
        ]);
        $scheduleId = $this->seedSchedule($schoolId, $yearId, (int) $section->id, $subjectId, $teacher['teacher_id'], $periodId);

        return [
            'school_id' => $schoolId,
            'year_id' => $yearId,
            'section_id' => (int) $section->id,
            'class_id' => (int) $class->id,
            'subject_id' => $subjectId,
            'period_id' => $periodId,
            'schedule_id' => $scheduleId,
            'teacher_id' => $teacher['teacher_id'],
            'teacher_user' => $teacher['user'],
            'enrollments' => $enrollments,
            'students' => $studentIds,
        ];
    }

    /**
     * @return array{teacher_id: int, user: User}
     */
    protected function seedTeacher(int $schoolId, int $yearId): array
    {
        $user = User::factory()->create();
        $teacherId = (int) DB::table('teachers.teachers')->insertGetId([
            'user_id' => $user->id, 'employee_code' => 'T'.substr(uniqid(), -6), 'first_name' => 'معلم', 'last_name' => 'اختبار',
            'full_name' => 'معلم اختبار', 'status' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('teachers.teacher_schools')->insert([
            'teacher_id' => $teacherId, 'school_id' => $schoolId, 'academic_year_id' => $yearId, 'is_primary' => true, 'created_at' => now(),
        ]);

        return ['teacher_id' => $teacherId, 'user' => $user];
    }

    protected function seedSchedule(int $schoolId, int $yearId, int $sectionId, int $subjectId, int $teacherId, int $periodId, int $dayOfWeek = 5): int
    {
        return (int) DB::table('timetable.schedules')->insertGetId([
            'school_id' => $schoolId, 'section_id' => $sectionId, 'academic_year_id' => $yearId, 'day_of_week' => $dayOfWeek,
            'period_id' => $periodId, 'subject_id' => $subjectId, 'teacher_id' => $teacherId, 'lifecycle_status' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function actAsAttendanceTeacher(User $user, int $schoolId): void
    {
        app(SecurityPermissionSeeder::class)->grantAttendanceTeacher($user, $schoolId);
        Sanctum::actingAs($user);
        $this->actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);
    }

    protected function actAsAttendanceManager(User $user, int $schoolId): void
    {
        app(SecurityPermissionSeeder::class)->grantAttendanceManager($user, $schoolId);
        Sanctum::actingAs($user);
        $this->actingAs($user);
        $this->withHeader('X-School-Id', (string) $schoolId);
    }

    /**
     * @param  list<int>  $enrollments
     * @param  list<int>  $statuses
     * @return array<string, mixed>
     */
    protected function marksPayload(int $version, array $enrollments, array $statuses, bool $confirm = false): array
    {
        $marks = [];
        foreach ($enrollments as $i => $enrollmentId) {
            $marks[] = ['enrollment_id' => $enrollmentId, 'status' => $statuses[$i] ?? 0];
        }

        return ['version' => $version, 'marks' => $marks] + ($confirm ? ['confirm' => true] : []);
    }

    protected function sheet(int $sessionId): object
    {
        return DB::table('attendance.sessions')->where('id', $sessionId)->first();
    }
}
