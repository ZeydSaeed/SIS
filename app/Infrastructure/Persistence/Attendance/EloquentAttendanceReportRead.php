<?php

namespace App\Infrastructure\Persistence\Attendance;

use App\Application\Attendance\Contracts\AttendanceAnalyticsReadInterface;
use App\Application\Attendance\Contracts\AttendanceReportReadInterface;
use App\Infrastructure\Persistence\Attendance\Support\FilterValues;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Report and alert aggregates over attendance.records (partitioned by academic year — every query pins the year
 * for partition pruning) joined to their sessions. Counting rule: session not cancelled, sheet not rejected,
 * status decided (> 0); «approved_only» narrows to approved sheets.
 */
final class EloquentAttendanceReportRead implements AttendanceAnalyticsReadInterface, AttendanceReportReadInterface
{
    private const STATUS_COUNTS = '
        COUNT(*) FILTER (WHERE r.status = 1) AS present, COUNT(*) FILTER (WHERE r.status = 2) AS absent,
        COUNT(*) FILTER (WHERE r.status = 3) AS late, COUNT(*) FILTER (WHERE r.status = 4) AS excused,
        COUNT(*) FILTER (WHERE r.status = 5) AS left_early';

    public function report(int $schoolId, int $academicYearId, string $type, array $filters, int $limit, int $offset): array
    {
        $query = match ($type) {
            'students' => $this->counted($schoolId, $academicYearId, $filters)
                ->join('students.students as st', 'st.id', '=', 'r.student_id')
                ->groupBy('r.student_id', 'st.full_name', 'st.student_code')
                ->selectRaw('r.student_id, st.full_name, st.student_code, '.self::STATUS_COUNTS)
                ->orderByRaw('COUNT(*) FILTER (WHERE r.status IN (2, 4)) DESC, st.full_name'),
            'student_detail' => $this->counted($schoolId, $academicYearId, $filters)
                ->leftJoin('curriculum.subjects as subj', 'subj.id', '=', 's.subject_id')
                ->leftJoin('timetable.periods as p', 'p.id', '=', 's.period_id')
                ->join('students.students as st', 'st.id', '=', 'r.student_id')
                ->selectRaw('r.attendance_date::text AS date, st.full_name, st.student_code, subj.name AS subject_name, p.period_number, r.status, r.minutes_late, r.notes, s.sheet_status, s.lesson_type')
                ->orderByDesc('r.attendance_date')->orderBy('p.period_number'),
            'sections' => $this->counted($schoolId, $academicYearId, $filters)
                ->join('enrollment.sections as sec', 'sec.id', '=', 's.section_id')
                ->join('enrollment.classes as c', 'c.id', '=', 'sec.class_id')
                ->groupBy('s.section_id', 'sec.name', 'c.name')
                ->selectRaw('s.section_id, sec.name AS section_name, c.name AS class_name, '.self::STATUS_COUNTS)
                ->orderBy('c.name')->orderBy('sec.name'),
            'subjects' => $this->counted($schoolId, $academicYearId, $filters)
                ->join('curriculum.subjects as subj', 'subj.id', '=', 's.subject_id')
                ->groupBy('s.subject_id', 'subj.name')
                ->selectRaw('s.subject_id, subj.name AS subject_name, '.self::STATUS_COUNTS)
                ->orderBy('subj.name'),
            'daily' => $this->counted($schoolId, $academicYearId, $filters)
                ->groupBy('r.attendance_date')
                ->selectRaw('r.attendance_date::text AS date, '.self::STATUS_COUNTS)
                ->orderByDesc('r.attendance_date'),
            'lesson_types' => $this->counted($schoolId, $academicYearId, $filters)
                ->groupBy('s.lesson_type')
                ->selectRaw('COALESCE(s.lesson_type, 1) AS lesson_type, '.self::STATUS_COUNTS)
                ->orderBy('lesson_type'),
            'periods' => $this->counted($schoolId, $academicYearId, $filters)
                ->leftJoin('timetable.periods as p', 'p.id', '=', 's.period_id')
                ->groupByRaw('EXTRACT(DOW FROM r.attendance_date), p.period_number')
                ->selectRaw('(EXTRACT(DOW FROM r.attendance_date)::int + 1) AS weekday, p.period_number, '.self::STATUS_COUNTS)
                ->orderBy('weekday')->orderBy('p.period_number'),
            'teachers' => $this->teacherSheets($schoolId, $academicYearId, $filters),
            'staff' => $this->staff($schoolId, $academicYearId, $filters),
            'excuses' => $this->excuseSummary($schoolId, $academicYearId, $filters),
            default => throw new \InvalidArgumentException("Report {$type} is computed in the Application layer."),
        };

        $total = DB::query()->fromSub((clone $query)->reorder(), 'x')->count();
        $rows = $query->limit($limit)->offset($offset)->get()->map(static fn (object $r): array => (array) $r)->all();

        return ['rows' => $rows, 'total' => $total];
    }

    public function schoolTotals(int $schoolId, int $academicYearId, string $from, string $to): array
    {
        $counts = (array) $this->counted($schoolId, $academicYearId, ['date_from' => $from, 'date_to' => $to])->selectRaw(self::STATUS_COUNTS)->first();
        $sheets = (array) DB::table('attendance.sessions')
            ->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)
            ->whereBetween('session_date', [$from, $to])->where('status', '<>', 3)
            ->selectRaw('COUNT(*) AS sheets, COUNT(*) FILTER (WHERE sheet_status = 1) AS drafts, COUNT(*) FILTER (WHERE sheet_status = 2) AS submitted,
                COUNT(*) FILTER (WHERE sheet_status = 3) AS under_review, COUNT(*) FILTER (WHERE sheet_status = 4) AS approved,
                COUNT(*) FILTER (WHERE sheet_status = 5) AS returned, COUNT(*) FILTER (WHERE sheet_status = 6) AS rejected,
                COUNT(*) FILTER (WHERE submitted_late) AS late_submissions')
            ->first();

        return array_map(static fn ($v): int => (int) $v, $counts + $sheets);
    }

    public function topAbsentees(int $schoolId, int $academicYearId, string $from, string $to, int $limit): array
    {
        return $this->counted($schoolId, $academicYearId, ['date_from' => $from, 'date_to' => $to])
            ->join('students.students as st', 'st.id', '=', 'r.student_id')
            ->groupBy('r.student_id', 'st.full_name', 'st.student_code')
            ->havingRaw('COUNT(*) FILTER (WHERE r.status IN (2, 4)) > 0')
            ->selectRaw('r.student_id, st.full_name, st.student_code, COUNT(*) FILTER (WHERE r.status = 2) AS absent, COUNT(*) FILTER (WHERE r.status = 4) AS excused, COUNT(*) AS decided')
            ->orderByRaw('COUNT(*) FILTER (WHERE r.status IN (2, 4)) DESC')
            ->limit($limit)
            ->get()->map(static fn (object $r): array => (array) $r)->all();
    }

    public function riskInputs(int $schoolId, int $academicYearId, string $today, int $subjectThreshold, array $filters): array
    {
        $recentFrom = (new \DateTimeImmutable($today))->modify('-29 days')->format('Y-m-d');
        $previousFrom = (new \DateTimeImmutable($today))->modify('-59 days')->format('Y-m-d');
        $subjectsOver = DB::query()->fromSub(
            $this->counted($schoolId, $academicYearId, ['date_from' => $recentFrom, 'date_to' => $today, 'status' => [2, 4]] + $filters)
                ->groupBy('r.student_id', 's.subject_id')->havingRaw('COUNT(*) >= ?', [$subjectThreshold])->selectRaw('r.student_id'),
            'so',
        )->groupBy('so.student_id')->selectRaw('so.student_id, COUNT(*) AS n');

        return $this->counted($schoolId, $academicYearId, ['date_from' => $previousFrom, 'date_to' => $today] + $filters)
            ->join('students.students as st', 'st.id', '=', 'r.student_id')
            ->leftJoinSub($subjectsOver, 'so', 'so.student_id', '=', 'r.student_id')
            ->groupBy('r.student_id', 'st.full_name', 'st.student_code', 'so.n')
            ->havingRaw('COUNT(*) FILTER (WHERE r.status IN (2, 4)) > 0')
            ->selectRaw("r.student_id, st.full_name, st.student_code,
                COUNT(*) FILTER (WHERE r.status IN (2, 4) AND r.attendance_date >= ?) AS recent_absences,
                COUNT(*) FILTER (WHERE r.attendance_date >= ?) AS recent_decided,
                COUNT(*) FILTER (WHERE r.status IN (2, 4) AND r.attendance_date < ?) AS previous_absences,
                COUNT(*) FILTER (WHERE r.attendance_date < ?) AS previous_decided,
                array_to_json(array_agg(DISTINCT r.attendance_date::text) FILTER (WHERE r.status IN (2, 4) AND r.attendance_date >= ?)) AS absence_dates,
                COALESCE(so.n, 0) AS subjects_over,
                COUNT(*) FILTER (WHERE r.status IN (2, 4) AND s.lesson_type = 2 AND r.attendance_date >= ?) AS practical_absences",
                [$recentFrom, $recentFrom, $recentFrom, $recentFrom, $recentFrom, $recentFrom])
            ->limit(2000)
            ->get()
            ->map(static fn (object $r): array => [
                'student_id' => (int) $r->student_id,
                'full_name' => (string) $r->full_name,
                'student_code' => (string) $r->student_code,
                'recent_absences' => (int) $r->recent_absences,
                'recent_decided' => (int) $r->recent_decided,
                'previous_absences' => (int) $r->previous_absences,
                'previous_decided' => (int) $r->previous_decided,
                'absence_dates' => array_values(array_filter((array) json_decode((string) ($r->absence_dates ?? '[]'), true))),
                'subjects_over' => (int) $r->subjects_over,
                'practical_absences' => (int) $r->practical_absences,
            ])
            ->all();
    }

    public function studentWindowStats(int $schoolId, int $academicYearId, string $from, string $to): array
    {
        return $this->counted($schoolId, $academicYearId, ['date_from' => $from, 'date_to' => $to])
            ->groupBy('r.student_id')
            ->selectRaw('r.student_id, COUNT(*) FILTER (WHERE r.status = 2) AS absences, COUNT(*) AS decided, COUNT(*) FILTER (WHERE r.status = 3) AS lates,
                COUNT(*) FILTER (WHERE r.status = 2 AND s.lesson_type = 2) AS practical_absences')
            ->havingRaw('COUNT(*) FILTER (WHERE r.status IN (2, 3)) > 0')
            ->get()->map(static fn (object $r): array => array_map('intval', (array) $r))->all();
    }

    public function studentSubjectAbsences(int $schoolId, int $academicYearId, string $from, string $to): array
    {
        return $this->counted($schoolId, $academicYearId, ['date_from' => $from, 'date_to' => $to, 'status' => 2])
            ->groupBy('r.student_id', 's.subject_id')
            ->selectRaw('r.student_id, s.subject_id, COUNT(*) AS absences')
            ->get()->map(static fn (object $r): array => array_map('intval', (array) $r))->all();
    }

    public function attendanceConflicts(int $schoolId, int $academicYearId, string $date): array
    {
        return $this->counted($schoolId, $academicYearId, ['date_from' => $date, 'date_to' => $date])
            ->whereIn('r.status', [1, 3, 5])
            ->whereNotNull('s.period_id')
            ->groupBy('r.student_id', 's.period_id')
            ->havingRaw('COUNT(DISTINCT r.session_id) > 1')
            ->selectRaw('r.student_id, array_to_json(array_agg(DISTINCT r.session_id)) AS session_ids')
            ->get()
            ->map(static fn (object $r): array => ['student_id' => (int) $r->student_id, 'session_ids' => array_map('intval', (array) json_decode((string) $r->session_ids, true)), 'date' => $date])
            ->all();
    }

    public function sectionDay(int $schoolId, int $academicYearId, string $date): array
    {
        return $this->counted($schoolId, $academicYearId, ['date_from' => $date, 'date_to' => $date])
            ->groupBy('s.section_id')
            ->selectRaw('s.section_id, COUNT(*) FILTER (WHERE r.status IN (2, 4)) AS absent, COUNT(*) AS total')
            ->get()->map(static fn (object $r): array => array_map('intval', (array) $r))->all();
    }

    public function sheetStatusesOn(int $schoolId, string $date): array
    {
        return DB::table('attendance.sessions')
            ->where('school_id', $schoolId)->where('session_date', $date)->whereNotNull('schedule_id')->where('status', '<>', 3)
            ->pluck('sheet_status', 'schedule_id')->map(static fn ($v): int => (int) $v)->all();
    }

    public function returnedCounts(int $schoolId, int $academicYearId, string $from, string $to): array
    {
        return DB::table('attendance.sheet_events as e')
            ->join('attendance.sessions as s', 's.id', '=', 'e.session_id')
            ->where('e.school_id', $schoolId)->where('e.academic_year_id', $academicYearId)->where('e.event_type', 6)
            ->whereBetween('s.session_date', [$from, $to])
            ->groupBy('s.teacher_id')
            ->selectRaw('s.teacher_id, COUNT(*) AS n')
            ->pluck('n', 'teacher_id')->map(static fn ($v): int => (int) $v)->all();
    }

    public function unavailableTeachers(int $schoolId, string $date): array
    {
        return DB::table('attendance.staff_records')
            ->where('school_id', $schoolId)->where('work_date', $date)->whereIn('status', [2, 5, 6, 7])
            ->pluck('teacher_id')->map(static fn ($v): int => (int) $v)->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function counted(int $schoolId, int $academicYearId, array $filters): Builder
    {
        $ids = static fn (string $key): array => FilterValues::ids($filters, $key);

        return DB::table('attendance.records as r')
            ->join('attendance.sessions as s', 's.id', '=', 'r.session_id')
            ->where('r.school_id', $schoolId)
            ->where('r.academic_year_id', $academicYearId)
            ->where('r.status', '>', 0)
            ->where('s.status', '<>', 3)
            ->where('s.sheet_status', '<>', 6)
            ->when(! empty($filters['approved_only']), static fn (Builder $q) => $q->where('s.sheet_status', 4))
            ->when(! empty($filters['date_from']), static fn (Builder $q) => $q->where('r.attendance_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), static fn (Builder $q) => $q->where('r.attendance_date', '<=', $filters['date_to']))
            ->when($ids('section_id') !== [], static fn (Builder $q) => $q->whereIn('s.section_id', $ids('section_id')))
            ->when($ids('subject_id') !== [], static fn (Builder $q) => $q->whereIn('s.subject_id', $ids('subject_id')))
            ->when($ids('teacher_id') !== [], static fn (Builder $q) => $q->whereIn('s.teacher_id', $ids('teacher_id')))
            ->when($ids('student_id') !== [], static fn (Builder $q) => $q->whereIn('r.student_id', $ids('student_id')))
            ->when($ids('lesson_type') !== [], static fn (Builder $q) => $q->whereIn('s.lesson_type', $ids('lesson_type')))
            ->when($ids('status') !== [], static fn (Builder $q) => $q->whereIn('r.status', $ids('status')))
            ->when($ids('class_id') !== [], static fn (Builder $q) => $q->whereIn('s.section_id', static fn (Builder $sub) => $sub->select('id')->from('enrollment.sections')->whereIn('class_id', $ids('class_id'))));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function teacherSheets(int $schoolId, int $academicYearId, array $filters): Builder
    {
        $records = $this->counted($schoolId, $academicYearId, $filters)->groupBy('s.teacher_id')->selectRaw('s.teacher_id, '.self::STATUS_COUNTS);

        return DB::table('attendance.sessions as s')
            ->join('teachers.teachers as t', 't.id', '=', 's.teacher_id')
            ->leftJoinSub($records, 'rc', 'rc.teacher_id', '=', 's.teacher_id')
            ->where('s.school_id', $schoolId)->where('s.academic_year_id', $academicYearId)->where('s.status', '<>', 3)
            ->when(! empty($filters['date_from']), static fn (Builder $q) => $q->where('s.session_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), static fn (Builder $q) => $q->where('s.session_date', '<=', $filters['date_to']))
            ->when(FilterValues::ids($filters, 'teacher_id') !== [], static fn (Builder $q) => $q->whereIn('s.teacher_id', FilterValues::ids($filters, 'teacher_id')))
            ->groupBy('s.teacher_id', 't.full_name', 'rc.present', 'rc.absent', 'rc.late', 'rc.excused', 'rc.left_early')
            ->selectRaw('s.teacher_id, t.full_name AS teacher_name, COUNT(*) AS sheets, COUNT(*) FILTER (WHERE s.sheet_status IN (1, 5)) AS pending,
                COUNT(*) FILTER (WHERE s.sheet_status IN (2, 3)) AS in_review, COUNT(*) FILTER (WHERE s.sheet_status = 4) AS approved,
                COUNT(*) FILTER (WHERE s.sheet_status = 6) AS rejected, SUM(GREATEST(s.submission_count - 1, 0)) AS resubmissions,
                COUNT(*) FILTER (WHERE s.submitted_late) AS late_submissions,
                COALESCE(rc.present, 0) AS present, COALESCE(rc.absent, 0) AS absent, COALESCE(rc.late, 0) AS late,
                COALESCE(rc.excused, 0) AS excused, COALESCE(rc.left_early, 0) AS left_early')
            ->orderBy('t.full_name');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function staff(int $schoolId, int $academicYearId, array $filters): Builder
    {
        return DB::table('attendance.staff_records as sr')
            ->join('teachers.teachers as t', 't.id', '=', 'sr.teacher_id')
            ->where('sr.school_id', $schoolId)->where('sr.academic_year_id', $academicYearId)
            ->when(! empty($filters['date_from']), static fn (Builder $q) => $q->where('sr.work_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), static fn (Builder $q) => $q->where('sr.work_date', '<=', $filters['date_to']))
            ->when(FilterValues::ids($filters, 'teacher_id') !== [], static fn (Builder $q) => $q->whereIn('sr.teacher_id', FilterValues::ids($filters, 'teacher_id')))
            ->groupBy('sr.teacher_id', 't.full_name')
            ->selectRaw('sr.teacher_id, t.full_name AS teacher_name, COUNT(*) AS days,
                COUNT(*) FILTER (WHERE sr.status = 1) AS present, COUNT(*) FILTER (WHERE sr.status = 2) AS absent,
                COUNT(*) FILTER (WHERE sr.status = 3) AS late, COUNT(*) FILTER (WHERE sr.status = 4) AS left_early,
                COUNT(*) FILTER (WHERE sr.status = 5) AS approved_leave, COUNT(*) FILTER (WHERE sr.status = 6) AS official_mission,
                COUNT(*) FILTER (WHERE sr.status = 7) AS excused_absence, COALESCE(SUM(sr.minutes_late), 0) AS minutes_late')
            ->orderBy('t.full_name');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function excuseSummary(int $schoolId, int $academicYearId, array $filters): Builder
    {
        return DB::table('attendance.excuses as x')
            ->where('x.school_id', $schoolId)->where('x.academic_year_id', $academicYearId)
            ->when(! empty($filters['date_from']), static fn (Builder $q) => $q->where('x.date_to', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), static fn (Builder $q) => $q->where('x.date_from', '<=', $filters['date_to']))
            ->groupBy('x.excuse_type', 'x.status')
            ->selectRaw('x.excuse_type, x.status, COUNT(*) AS excuses, COALESCE(SUM(x.applied_records), 0) AS applied_records')
            ->orderBy('x.excuse_type')->orderBy('x.status');
    }
}
