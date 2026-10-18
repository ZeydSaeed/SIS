<?php

namespace App\Infrastructure\Persistence\Attendance;

use App\Domain\Attendance\Data\AttendanceSheetSnapshot;
use App\Domain\Attendance\Data\LessonSlot;
use App\Domain\Attendance\Data\RosterEntry;
use App\Domain\Attendance\Repositories\AttendanceSheetRepositoryInterface;
use App\Domain\Attendance\ValueObjects\SessionStatus;
use App\Domain\Attendance\ValueObjects\SheetEventType;
use App\Domain\Attendance\ValueObjects\SheetStatus;
use Illuminate\Support\Facades\DB;

final class EloquentAttendanceSheetRepository implements AttendanceSheetRepositoryInterface
{
    /** Enrollment statuses never on a roster: 0 inactive, 2 cancelled (dates decide the rest — ATT-D4). */
    private const OFF_ROSTER_ENROLLMENT = [0, 2];

    public function lockSheet(int $sessionId): ?AttendanceSheetSnapshot
    {
        $row = DB::selectOne('
            SELECT s.id, s.school_id, s.academic_year_id, s.section_id, s.subject_id, s.teacher_id, s.session_date::text AS session_date,
                   s.period_id, s.status, s.sheet_status, s.version, s.submission_count, s.schedule_id, s.group_id, s.policy_id,
                   s.lesson_snapshot->>\'co_teacher_id\' AS co_teacher_id,
                   COALESCE(s.lesson_snapshot->>\'start_time\', to_char(p.start_time, \'HH24:MI\')) AS start_time,
                   COALESCE(s.lesson_snapshot->>\'end_time\', to_char(p.end_time, \'HH24:MI\')) AS end_time
            FROM attendance.sessions s
            LEFT JOIN timetable.periods p ON p.id = s.period_id
            WHERE s.id = ?
            FOR UPDATE OF s
        ', [$sessionId]);

        if ($row === null) {
            return null;
        }

        return new AttendanceSheetSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            academicYearId: (int) $row->academic_year_id,
            sectionId: (int) $row->section_id,
            subjectId: (int) $row->subject_id,
            teacherId: (int) $row->teacher_id,
            coTeacherId: $row->co_teacher_id !== null && $row->co_teacher_id !== '' ? (int) $row->co_teacher_id : null,
            sessionDate: (string) $row->session_date,
            periodId: $row->period_id !== null ? (int) $row->period_id : null,
            status: (int) $row->status,
            sheetStatus: (int) $row->sheet_status,
            version: (int) $row->version,
            submissionCount: (int) $row->submission_count,
            scheduleId: $row->schedule_id !== null ? (int) $row->schedule_id : null,
            groupId: $row->group_id !== null ? (int) $row->group_id : null,
            policyId: $row->policy_id !== null ? (int) $row->policy_id : null,
            startTime: $row->start_time !== null ? (string) $row->start_time : null,
            endTime: $row->end_time !== null ? (string) $row->end_time : null,
        );
    }

    public function findLessonSession(int $schoolId, int $scheduleId, string $date): ?int
    {
        $id = DB::table('attendance.sessions')
            ->where('school_id', $schoolId)
            ->where('schedule_id', $scheduleId)
            ->where('session_date', $date)
            ->where('status', '<>', SessionStatus::Cancelled->value)
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function openLessonSession(LessonSlot $lesson, int $schoolId, int $academicYearId, ?int $policyId, int $origin, ?int $openedBy): array
    {
        $row = DB::selectOne('
            INSERT INTO attendance.sessions
                (section_id, subject_id, academic_year_id, session_date, period_id, teacher_id, status, school_id,
                 sheet_status, version, origin, schedule_id, group_id, lesson_type, lesson_snapshot, policy_id, opened_by, created_at, updated_at)
            VALUES (?, ?, ?, ?::date, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?::jsonb, ?, ?, NOW(), NOW())
            ON CONFLICT (school_id, schedule_id, session_date) WHERE schedule_id IS NOT NULL AND status <> 3 DO NOTHING
            RETURNING id
        ', [
            $lesson->sectionId, $lesson->subjectId, $academicYearId, $lesson->date, $lesson->periodId, $lesson->effectiveTeacherId(),
            SessionStatus::Open->value, $schoolId, SheetStatus::Draft->value, $origin, $lesson->scheduleId, $lesson->groupId,
            $lesson->lessonType, json_encode($lesson->toArray(), JSON_UNESCAPED_UNICODE), $policyId, $openedBy,
        ]);

        if ($row !== null) {
            return ['id' => (int) $row->id, 'created' => true];
        }

        return ['id' => (int) $this->findLessonSession($schoolId, $lesson->scheduleId, $lesson->date), 'created' => false];
    }

    public function touchVersion(int $sessionId): array
    {
        $row = DB::selectOne('
            UPDATE attendance.sessions SET version = version + 1, updated_at = NOW() WHERE id = ? RETURNING version, sheet_status
        ', [$sessionId]);

        return ['version' => (int) ($row->version ?? 0), 'sheet_status' => (int) ($row->sheet_status ?? SheetStatus::Draft->value)];
    }

    public function roster(int $schoolId, int $academicYearId, int $sectionId, ?int $groupId, string $date): array
    {
        $query = DB::table('enrollment.enrollments as e')
            ->where('e.school_id', $schoolId)
            ->where('e.academic_year_id', $academicYearId)
            ->where('e.section_id', $sectionId)
            ->whereNotIn('e.status', self::OFF_ROSTER_ENROLLMENT)
            ->where('e.effective_from', '<=', $date)
            ->where(static fn ($q) => $q->whereNull('e.effective_to')->orWhere('e.effective_to', '>=', $date));

        if ($groupId !== null && $this->groupHasMembers($groupId, $date)) {
            $query->whereIn('e.id', static fn ($q) => $q->select('gm.enrollment_id')->from('timetable.group_members as gm')
                ->where('gm.group_id', $groupId)
                ->where('gm.status', 1)
                ->where('gm.effective_from', '<=', $date)
                ->where(static fn ($w) => $w->whereNull('gm.effective_to')->orWhere('gm.effective_to', '>=', $date)));
        }

        return $query->orderBy('e.id')->get(['e.id', 'e.student_id'])
            ->map(static fn (object $r): RosterEntry => new RosterEntry((int) $r->id, (int) $r->student_id))
            ->all();
    }

    public function statuses(int $sessionId, int $academicYearId): array
    {
        return DB::table('attendance.records')
            ->where('session_id', $sessionId)
            ->where('academic_year_id', $academicYearId)
            ->pluck('status', 'enrollment_id')
            ->map(static fn ($status): int => (int) $status)
            ->all();
    }

    public function saveMarks(AttendanceSheetSnapshot $sheet, array $marks, ?int $recordedBy, int $markedVia): int
    {
        $now = now();
        $rows = array_map(static fn (array $m): array => [
            'session_id' => $sheet->id,
            'student_id' => $m['student_id'],
            'enrollment_id' => $m['mark']->enrollmentId,
            'academic_year_id' => $sheet->academicYearId,
            'school_id' => $sheet->schoolId,
            'attendance_date' => $sheet->sessionDate,
            'status' => $m['mark']->status,
            'minutes_late' => $m['mark']->minutesLate,
            'notes' => $m['mark']->note,
            'marked_via' => $markedVia,
            'recorded_by' => $recordedBy,
            'created_at' => $now,
            'updated_at' => $now,
        ], $marks);

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('attendance.records')->upsert(
                $chunk,
                ['session_id', 'student_id', 'academic_year_id'],
                ['status', 'minutes_late', 'notes', 'marked_via', 'recorded_by', 'updated_at'],
            );
        }

        return count($rows);
    }

    public function bumpVersion(int $sessionId, int $expectedVersion): bool
    {
        return DB::table('attendance.sessions')
            ->where('id', $sessionId)
            ->where('version', $expectedVersion)
            ->update(['version' => $expectedVersion + 1, 'updated_at' => now()]) === 1;
    }

    public function transition(int $sessionId, int $expectedVersion, SheetStatus $to, array $stamps): bool
    {
        $allowed = array_intersect_key($stamps, array_flip([
            'submitted_at', 'submitted_by', 'submitted_late', 'submission_count', 'reviewed_at', 'reviewed_by', 'review_note', 'status',
        ]));

        return DB::table('attendance.sessions')
            ->where('id', $sessionId)
            ->where('version', $expectedVersion)
            ->update($allowed + ['sheet_status' => $to->value, 'version' => $expectedVersion + 1, 'updated_at' => now()]) === 1;
    }

    public function appendEvent(int $schoolId, int $academicYearId, int $sessionId, SheetEventType $type, ?int $fromStatus, int $toStatus, int $version, ?int $actorUserId, ?string $note, ?array $counts): void
    {
        DB::table('attendance.sheet_events')->insert([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'session_id' => $sessionId,
            'event_type' => $type->value,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'version' => $version,
            'actor_user_id' => $actorUserId,
            'note' => $note,
            'counts' => $counts !== null ? json_encode($counts) : null,
            'created_at' => now(),
        ]);
    }

    public function isHoliday(int $schoolId, int $academicYearId, string $date): bool
    {
        return DB::table('academic.holidays')
            ->where('academic_year_id', $academicYearId)
            ->where('status', 1)
            ->where(static fn ($q) => $q->whereNull('school_id')->orWhere('school_id', $schoolId))
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->exists();
    }

    public function exceptionalLesson(int $schoolId, int $academicYearId, int $sectionId, int $subjectId, int $teacherId, int $periodId): ?array
    {
        $row = DB::selectOne("
            SELECT sec.id AS section_id, sec.name AS section_name, c.name AS class_name, subj.id AS subject_id, subj.name AS subject_name,
                   subj.subject_type, t.id AS teacher_id, t.full_name AS teacher_name, p.id AS period_id, p.period_number,
                   to_char(p.start_time, 'HH24:MI') AS start_time, to_char(p.end_time, 'HH24:MI') AS end_time
            FROM enrollment.sections sec
            JOIN enrollment.classes c ON c.id = sec.class_id AND c.school_id = ? AND c.academic_year_id = ?
            JOIN curriculum.subjects subj ON subj.id = ? AND subj.status = 1
            JOIN teachers.teachers t ON t.id = ?
            JOIN teachers.teacher_schools ts ON ts.teacher_id = t.id AND ts.school_id = c.school_id AND ts.academic_year_id = c.academic_year_id AND ts.left_at IS NULL
            JOIN timetable.periods p ON p.id = ? AND p.school_id = c.school_id
            WHERE sec.id = ?
            LIMIT 1
        ", [$schoolId, $academicYearId, $subjectId, $teacherId, $periodId, $sectionId]);

        return $row === null ? null : (array) $row;
    }

    public function openExceptionalSession(int $schoolId, int $academicYearId, string $date, array $snapshot, ?int $policyId, ?int $openedBy): array
    {
        $row = DB::selectOne('
            INSERT INTO attendance.sessions
                (section_id, subject_id, academic_year_id, session_date, period_id, teacher_id, status, school_id,
                 sheet_status, version, origin, lesson_type, lesson_snapshot, policy_id, opened_by, created_at, updated_at)
            VALUES (?, ?, ?, ?::date, ?, ?, ?, ?, ?, 1, 3, ?, ?::jsonb, ?, ?, NOW(), NOW())
            ON CONFLICT (school_id, academic_year_id, section_id, subject_id, session_date, period_id, COALESCE(schedule_id, 0)) WHERE status = 1 DO NOTHING
            RETURNING id
        ', [
            $snapshot['section_id'], $snapshot['subject_id'], $academicYearId, $date, $snapshot['period_id'], $snapshot['teacher_id'],
            SessionStatus::Open->value, $schoolId, SheetStatus::Draft->value, $snapshot['lesson_type'], json_encode($snapshot, JSON_UNESCAPED_UNICODE), $policyId, $openedBy,
        ]);

        return ['id' => $row !== null ? (int) $row->id : 0, 'created' => $row !== null];
    }

    private function groupHasMembers(int $groupId, string $date): bool
    {
        return DB::table('timetable.group_members')
            ->where('group_id', $groupId)
            ->where('status', 1)
            ->where('effective_from', '<=', $date)
            ->where(static fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date))
            ->exists();
    }
}
