<?php

namespace App\Infrastructure\Persistence\Attendance;

use App\Application\Attendance\Contracts\AttendanceWorkspaceReadInterface;
use App\Infrastructure\Persistence\Attendance\Support\FilterValues;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class EloquentAttendanceWorkspaceRead implements AttendanceWorkspaceReadInterface
{
    /** Per-status counts of one session's records (status 0 = unmarked draft rows). */
    private const COUNTS_SQL = '
        (SELECT json_build_object(
            \'present\', COUNT(*) FILTER (WHERE r.status = 1), \'absent\', COUNT(*) FILTER (WHERE r.status = 2),
            \'late\', COUNT(*) FILTER (WHERE r.status = 3), \'excused\', COUNT(*) FILTER (WHERE r.status = 4),
            \'left_early\', COUNT(*) FILTER (WHERE r.status = 5), \'marked\', COUNT(*) FILTER (WHERE r.status > 0))
         FROM attendance.records r WHERE r.session_id = s.id AND r.academic_year_id = s.academic_year_id) AS counts';

    private const STAFF_CAP = 500;

    public function sessionsForLessons(int $schoolId, string $date, array $scheduleIds): array
    {
        if ($scheduleIds === []) {
            return [];
        }

        return DB::table('attendance.sessions as s')
            ->where('s.school_id', $schoolId)
            ->where('s.session_date', $date)
            ->whereIn('s.schedule_id', $scheduleIds)
            ->where('s.status', '<>', 3)
            ->select(['s.id', 's.schedule_id', 's.sheet_status', 's.status', 's.version', 's.submitted_at', 's.submitted_late', 's.review_note'])
            ->selectRaw(self::COUNTS_SQL)
            ->get()
            ->mapWithKeys(static fn (object $r): array => [(int) $r->schedule_id => [
                'session_id' => (int) $r->id,
                'sheet_status' => (int) $r->sheet_status,
                'status' => (int) $r->status,
                'version' => (int) $r->version,
                'submitted_at' => $r->submitted_at,
                'submitted_late' => (bool) $r->submitted_late,
                'review_note' => $r->review_note,
                'counts' => json_decode((string) $r->counts, true),
            ]])
            ->all();
    }

    public function sheetHeader(int $schoolId, int $sessionId): ?array
    {
        $row = DB::table('attendance.sessions as s')
            ->leftJoin('curriculum.subjects as subj', 'subj.id', '=', 's.subject_id')
            ->leftJoin('enrollment.sections as sec', 'sec.id', '=', 's.section_id')
            ->leftJoin('enrollment.classes as c', 'c.id', '=', 'sec.class_id')
            ->leftJoin('teachers.teachers as t', 't.id', '=', 's.teacher_id')
            ->leftJoin('timetable.periods as p', 'p.id', '=', 's.period_id')
            ->leftJoin('users as us', 'us.id', '=', 's.submitted_by')
            ->leftJoin('users as ur', 'ur.id', '=', 's.reviewed_by')
            ->where('s.id', $sessionId)
            ->where('s.school_id', $schoolId)
            ->select([
                's.id', 's.school_id', 's.academic_year_id', 's.section_id', 's.subject_id', 's.teacher_id', 's.period_id', 's.status', 's.sheet_status',
                's.version', 's.origin', 's.schedule_id', 's.group_id', 's.lesson_type', 's.policy_id', 's.submission_count', 's.submitted_at',
                's.submitted_late', 's.reviewed_at', 's.review_note', 's.lesson_snapshot',
                'subj.name as subject_name', 'sec.name as section_name', 'c.name as class_name', 't.full_name as teacher_name',
                'p.period_number', 'us.name as submitted_by_name', 'ur.name as reviewed_by_name',
            ])
            ->selectRaw('s.session_date::text AS session_date, to_char(p.start_time, \'HH24:MI\') AS period_start, to_char(p.end_time, \'HH24:MI\') AS period_end')
            ->first();
        if ($row === null) {
            return null;
        }
        $snapshot = $row->lesson_snapshot !== null ? (array) json_decode((string) $row->lesson_snapshot, true) : [];
        $data = (array) $row;
        unset($data['lesson_snapshot']);

        return array_merge($data, [
            'co_teacher_id' => isset($snapshot['co_teacher_id']) ? (int) $snapshot['co_teacher_id'] : null,
            'group_id' => $row->group_id !== null ? (int) $row->group_id : null,
            'policy_id' => $row->policy_id !== null ? (int) $row->policy_id : null,
            'start_time' => $snapshot['start_time'] ?? $row->period_start,
            'end_time' => $snapshot['end_time'] ?? $row->period_end,
            'room_name' => $snapshot['room_name'] ?? null,
            'group_name' => $snapshot['group_name'] ?? null,
            'period_number' => $snapshot['period_number'] ?? $row->period_number,
            'submitted_late' => (bool) $row->submitted_late,
        ]);
    }

    public function sheetRows(int $schoolId, int $sessionId, int $academicYearId, array $enrollmentIds): array
    {
        if ($enrollmentIds === []) {
            return [];
        }

        return DB::table('enrollment.enrollments as e')
            ->join('students.students as st', 'st.id', '=', 'e.student_id')
            ->leftJoin('attendance.records as r', function ($join) use ($sessionId, $academicYearId): void {
                $join->on('r.student_id', '=', 'e.student_id')->where('r.session_id', '=', $sessionId)->where('r.academic_year_id', '=', $academicYearId);
            })
            ->where('e.school_id', $schoolId)
            ->whereIn('e.id', $enrollmentIds)
            ->orderBy('st.full_name')
            ->get(['e.id as enrollment_id', 'e.student_id', 'st.student_code', 'st.full_name', 'r.status', 'r.minutes_late', 'r.notes', 'r.updated_at', 'r.excuse_id', 'r.marked_via'])
            ->map(static fn (object $r): array => [
                'enrollment_id' => (int) $r->enrollment_id,
                'student_id' => (int) $r->student_id,
                'student_code' => (string) $r->student_code,
                'full_name' => (string) $r->full_name,
                'status' => $r->status !== null ? (int) $r->status : 0,
                'minutes_late' => $r->minutes_late !== null ? (int) $r->minutes_late : null,
                'note' => $r->notes,
                'updated_at' => $r->updated_at,
                'excuse_id' => $r->excuse_id !== null ? (int) $r->excuse_id : null,
                'marked_via' => $r->marked_via !== null ? (int) $r->marked_via : null,
            ])
            ->all();
    }

    public function offRosterRows(int $schoolId, int $sessionId, int $academicYearId, array $enrollmentIds): array
    {
        return DB::table('attendance.records as r')
            ->join('students.students as st', 'st.id', '=', 'r.student_id')
            ->where('r.session_id', $sessionId)
            ->where('r.academic_year_id', $academicYearId)
            ->where('r.school_id', $schoolId)
            ->when($enrollmentIds !== [], static fn (Builder $q) => $q->whereNotIn('r.enrollment_id', $enrollmentIds))
            ->orderBy('st.full_name')
            ->get(['r.enrollment_id', 'r.student_id', 'st.student_code', 'st.full_name', 'r.status', 'r.notes'])
            ->map(static fn (object $r): array => (array) $r)
            ->all();
    }

    public function sheetEvents(int $schoolId, int $sessionId): array
    {
        return DB::table('attendance.sheet_events as e')
            ->leftJoin('users as u', 'u.id', '=', 'e.actor_user_id')
            ->where('e.school_id', $schoolId)
            ->where('e.session_id', $sessionId)
            ->orderBy('e.id')
            ->get(['e.id', 'e.event_type', 'e.from_status', 'e.to_status', 'e.version', 'e.note', 'e.counts', 'e.created_at', 'u.name as actor_name'])
            ->map(static fn (object $r): array => ['counts' => $r->counts !== null ? json_decode((string) $r->counts, true) : null] + (array) $r)
            ->all();
    }

    public function reviewBoard(int $schoolId, int $academicYearId, array $filters, int $page, int $perPage): array
    {
        $base = $this->boardQuery($schoolId, $academicYearId, $filters);
        $totals = (clone $base)->groupBy('s.sheet_status')->selectRaw('s.sheet_status, COUNT(*) AS n')->pluck('n', 'sheet_status')
            ->mapWithKeys(static fn ($n, $status): array => [(int) $status => (int) $n])->all();

        $statuses = array_values(array_filter(array_map('intval', (array) ($filters['sheet_status'] ?? [])), static fn (int $s): bool => $s >= 1 && $s <= 6));
        $listed = $statuses === [] ? $base : (clone $base)->whereIn('s.sheet_status', $statuses);
        $total = (clone $listed)->count();

        $rows = $listed
            ->leftJoin('curriculum.subjects as subj', 'subj.id', '=', 's.subject_id')
            ->leftJoin('enrollment.sections as sec', 'sec.id', '=', 's.section_id')
            ->leftJoin('enrollment.classes as c', 'c.id', '=', 'sec.class_id')
            ->leftJoin('teachers.teachers as t', 't.id', '=', 's.teacher_id')
            ->leftJoin('timetable.periods as p', 'p.id', '=', 's.period_id')
            ->select([
                's.id', 's.sheet_status', 's.status', 's.version', 's.lesson_type', 's.submitted_at', 's.submitted_late', 's.submission_count',
                's.reviewed_at', 's.review_note', 's.teacher_id', 'subj.name as subject_name', 'sec.name as section_name', 'c.name as class_name',
                't.full_name as teacher_name', 'p.period_number',
            ])
            ->selectRaw('s.session_date::text AS session_date, COALESCE(s.lesson_snapshot->>\'start_time\', to_char(p.start_time, \'HH24:MI\')) AS start_time')
            ->selectRaw(self::COUNTS_SQL)
            ->orderByDesc('s.session_date')
            ->orderBy('p.period_number')
            ->orderBy('s.id')
            ->forPage($page, $perPage)
            ->get()
            ->map(static fn (object $r): array => ['counts' => json_decode((string) $r->counts, true), 'submitted_late' => (bool) $r->submitted_late] + (array) $r)
            ->all();

        return [
            'data' => $rows,
            'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / max(1, $perPage)))],
            'totals' => $totals,
        ];
    }

    public function teacherNotices(int $schoolId, int $academicYearId, int $teacherId, int $limit): array
    {
        return DB::table('attendance.sessions as s')
            ->leftJoin('curriculum.subjects as subj', 'subj.id', '=', 's.subject_id')
            ->leftJoin('enrollment.sections as sec', 'sec.id', '=', 's.section_id')
            ->leftJoin('enrollment.classes as c', 'c.id', '=', 'sec.class_id')
            ->where('s.school_id', $schoolId)
            ->where('s.academic_year_id', $academicYearId)
            ->where('s.teacher_id', $teacherId)
            ->whereIn('s.sheet_status', [4, 5, 6])
            ->whereNotNull('s.reviewed_at')
            ->orderByRaw('CASE WHEN s.sheet_status = 5 THEN 0 ELSE 1 END')
            ->orderByDesc('s.reviewed_at')
            ->limit($limit)
            ->select(['s.id as session_id', 's.sheet_status', 's.review_note', 's.reviewed_at', 's.version', 'subj.name as subject_name', 'sec.name as section_name', 'c.name as class_name'])
            ->selectRaw('s.session_date::text AS session_date')
            ->get()
            ->map(static fn (object $r): array => (array) $r)
            ->all();
    }

    public function excuses(int $schoolId, int $academicYearId, array $filters, int $page, int $perPage): array
    {
        $query = DB::table('attendance.excuses as x')
            ->join('students.students as st', 'st.id', '=', 'x.student_id')
            ->join('enrollment.enrollments as e', 'e.id', '=', 'x.enrollment_id')
            ->leftJoin('enrollment.sections as sec', 'sec.id', '=', 'e.section_id')
            ->leftJoin('enrollment.classes as c', 'c.id', '=', 'e.class_id')
            ->leftJoin('users as us', 'us.id', '=', 'x.submitted_by')
            ->leftJoin('users as ur', 'ur.id', '=', 'x.reviewed_by')
            ->where('x.school_id', $schoolId)
            ->where('x.academic_year_id', $academicYearId)
            ->when(FilterValues::ids($filters, 'status') !== [], static fn (Builder $q) => $q->whereIn('x.status', FilterValues::ids($filters, 'status')))
            ->when(FilterValues::ids($filters, 'excuse_type') !== [], static fn (Builder $q) => $q->whereIn('x.excuse_type', FilterValues::ids($filters, 'excuse_type')))
            ->when(FilterValues::ids($filters, 'section_id') !== [], static fn (Builder $q) => $q->whereIn('e.section_id', FilterValues::ids($filters, 'section_id')))
            ->when(FilterValues::ids($filters, 'class_id') !== [], static fn (Builder $q) => $q->whereIn('e.class_id', FilterValues::ids($filters, 'class_id')))
            ->when(! empty($filters['date_from']), static fn (Builder $q) => $q->where('x.date_to', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), static fn (Builder $q) => $q->where('x.date_from', '<=', $filters['date_to']))
            ->when(! empty($filters['q']), static fn (Builder $q) => $q->where(static fn (Builder $w) => $w
                ->where('st.full_name', 'ilike', '%'.addcslashes((string) $filters['q'], '%_\\').'%')
                ->orWhere('st.student_code', 'ilike', addcslashes((string) $filters['q'], '%_\\').'%')));
        $total = (clone $query)->count();

        $rows = $query->orderByRaw('CASE WHEN x.status = 1 THEN 0 ELSE 1 END')->orderByDesc('x.submitted_at')->forPage($page, $perPage)
            ->get([
                'x.id', 'x.student_id', 'x.enrollment_id', 'x.session_id', 'x.excuse_type', 'x.reason', 'x.status', 'x.decision_note', 'x.applied_records',
                'x.version', 'x.submitted_at', 'x.reviewed_at', 'x.document_file_id', 'st.full_name', 'st.student_code', 'sec.name as section_name',
                'c.name as class_name', 'us.name as submitted_by_name', 'ur.name as reviewed_by_name',
            ])
            ->map(static fn (object $r): array => (array) $r)
            ->all();
        $dates = DB::table('attendance.excuses')->whereIn('id', array_column($rows, 'id'))->selectRaw('id, date_from::text AS date_from, date_to::text AS date_to')->get()->keyBy('id');
        foreach ($rows as $i => $row) {
            $rows[$i]['date_from'] = $dates[$row['id']]->date_from ?? null;
            $rows[$i]['date_to'] = $dates[$row['id']]->date_to ?? null;
        }

        return ['data' => $rows, 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / max(1, $perPage)))]];
    }

    public function staffDay(int $schoolId, int $academicYearId, string $date): array
    {
        return DB::table('teachers.teachers as t')
            ->join('teachers.teacher_schools as ts', 'ts.teacher_id', '=', 't.id')
            ->leftJoin('users as acc', 'acc.id', '=', 't.user_id')
            ->leftJoin('attendance.staff_records as sr', function ($join) use ($schoolId, $date): void {
                $join->on('sr.teacher_id', '=', 't.id')->where('sr.school_id', '=', $schoolId)->where('sr.work_date', '=', $date);
            })
            ->where('ts.school_id', $schoolId)
            ->where('ts.academic_year_id', $academicYearId)
            ->whereNull('ts.left_at')
            ->where('t.status', 1)
            ->orderBy('t.full_name')
            ->limit(self::STAFF_CAP)
            ->get([
                't.id as teacher_id', 't.full_name', 't.employee_code', 'acc.email as account_email', 'sr.id as record_id', 'sr.status', 'sr.check_in_at', 'sr.check_out_at',
                'sr.minutes_late', 'sr.method', 'sr.notes', 'sr.version',
            ])
            ->unique('teacher_id')
            ->values()
            ->map(static fn (object $r): array => (array) $r)
            ->all();
    }

    public function staffHistory(int $schoolId, int $teacherId, string $from, string $to): array
    {
        return DB::table('attendance.staff_records')
            ->where('school_id', $schoolId)
            ->where('teacher_id', $teacherId)
            ->whereBetween('work_date', [$from, $to])
            ->orderByDesc('work_date')
            ->select(['id', 'status', 'check_in_at', 'check_out_at', 'minutes_late', 'method', 'notes'])
            ->selectRaw('work_date::text AS work_date')
            ->get()
            ->map(static fn (object $r): array => (array) $r)
            ->all();
    }

    public function alerts(int $schoolId, int $academicYearId, array $filters, int $page, int $perPage): array
    {
        $query = DB::table('attendance.alerts as a')
            ->leftJoin('students.students as st', static fn ($j) => $j->on('st.id', '=', 'a.subject_id')->where('a.subject_type', '=', 'student'))
            ->leftJoin('teachers.teachers as t', static fn ($j) => $j->on('t.id', '=', 'a.subject_id')->where('a.subject_type', '=', 'teacher'))
            ->leftJoin('enrollment.sections as sec', static fn ($j) => $j->on('sec.id', '=', 'a.subject_id')->where('a.subject_type', '=', 'section'))
            ->leftJoin('enrollment.classes as c', 'c.id', '=', 'sec.class_id')
            ->where('a.school_id', $schoolId)
            ->where('a.academic_year_id', $academicYearId)
            ->when(FilterValues::ids($filters, 'status') !== [], static fn (Builder $q) => $q->whereIn('a.status', FilterValues::ids($filters, 'status')))
            ->when(! empty($filters['rule_code']), static fn (Builder $q) => $q->whereIn('a.rule_code', (array) $filters['rule_code']))
            ->when(FilterValues::ids($filters, 'severity') !== [], static fn (Builder $q) => $q->whereIn('a.severity', FilterValues::ids($filters, 'severity')));
        $total = (clone $query)->count();
        $open = (int) DB::table('attendance.alerts')->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->where('status', 1)->count();

        $rows = $query->orderBy('a.status')->orderByDesc('a.severity')->orderByDesc('a.id')->forPage($page, $perPage)
            ->get([
                'a.id', 'a.rule_code', 'a.severity', 'a.subject_type', 'a.subject_id', 'a.metric', 'a.threshold', 'a.details', 'a.status', 'a.resolution_note',
                'a.created_at', 'a.resolved_at', 'st.full_name as student_name', 'st.student_code', 't.full_name as teacher_name',
                'sec.name as section_name', 'c.name as class_name',
            ])
            ->map(static fn (object $r): array => ['details' => $r->details !== null ? json_decode((string) $r->details, true) : null] + (array) $r)
            ->all();
        $periods = DB::table('attendance.alerts')->whereIn('id', array_column($rows, 'id'))->selectRaw('id, period_start::text AS period_start, period_end::text AS period_end')->get()->keyBy('id');
        foreach ($rows as $i => $row) {
            $rows[$i]['period_start'] = $periods[$row['id']]->period_start ?? null;
            $rows[$i]['period_end'] = $periods[$row['id']]->period_end ?? null;
        }

        return ['data' => $rows, 'meta' => ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'last_page' => max(1, (int) ceil($total / max(1, $perPage)))], 'open' => $open];
    }

    public function exports(int $schoolId, int $userId, int $limit): array
    {
        return DB::table('attendance.report_exports')
            ->where('school_id', $schoolId)
            ->where('requested_by', $userId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'report_type', 'format', 'status', 'row_count', 'truncated', 'error', 'created_at', 'finished_at'])
            ->map(static fn (object $r): array => (array) $r)
            ->all();
    }

    public function lookups(int $schoolId, int $academicYearId): array
    {
        $sections = DB::table('enrollment.sections as s')
            ->join('enrollment.classes as c', 'c.id', '=', 's.class_id')
            ->where('c.school_id', $schoolId)
            ->where('c.academic_year_id', $academicYearId)
            ->orderBy('c.name')->orderBy('s.name')
            ->get(['c.id as class_id', 'c.name as class_name', 's.id', 's.name']);
        $classes = [];
        foreach ($sections as $s) {
            $classes[$s->class_id] ??= ['id' => (int) $s->class_id, 'name' => (string) $s->class_name, 'sections' => []];
            $classes[$s->class_id]['sections'][] = ['id' => (int) $s->id, 'name' => (string) $s->name];
        }

        $subjectIds = DB::table('timetable.schedules')->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->distinct()->pluck('subject_id')
            ->merge(DB::table('attendance.sessions')->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->distinct()->pluck('subject_id'))
            ->unique()->values()->all();

        return [
            'periods' => DB::table('timetable.periods')->where('school_id', $schoolId)->orderBy('period_number')
                ->selectRaw("id, period_number, to_char(start_time, 'HH24:MI') AS start_time, to_char(end_time, 'HH24:MI') AS end_time")
                ->get()->map(static fn (object $r): array => (array) $r)->all(),
            'classes' => array_values($classes),
            'branches' => DB::table('organization.branches')->where('school_id', $schoolId)->orderBy('name')->get(['id', 'name'])->map(static fn (object $r): array => (array) $r)->all(),
            'departments' => DB::table('organization.departments')->where('school_id', $schoolId)->orderBy('name')->get(['id', 'name'])->map(static fn (object $r): array => (array) $r)->all(),
            'subjects' => DB::table('curriculum.subjects')->whereIn('id', $subjectIds)->orderBy('name')->get(['id', 'name'])->map(static fn (object $r): array => (array) $r)->all(),
            'teachers' => DB::table('teachers.teachers as t')->join('teachers.teacher_schools as ts', 'ts.teacher_id', '=', 't.id')
                ->where('ts.school_id', $schoolId)->where('ts.academic_year_id', $academicYearId)->whereNull('ts.left_at')
                ->orderBy('t.full_name')->distinct()->get(['t.id', 't.full_name'])->map(static fn (object $r): array => (array) $r)->all(),
        ];
    }

    public function searchEnrollments(int $schoolId, int $academicYearId, string $term, int $limit): array
    {
        $like = addcslashes($term, '%_\\');

        return DB::table('enrollment.enrollments as e')
            ->join('students.students as st', 'st.id', '=', 'e.student_id')
            ->leftJoin('enrollment.sections as sec', 'sec.id', '=', 'e.section_id')
            ->leftJoin('enrollment.classes as c', 'c.id', '=', 'e.class_id')
            ->where('e.school_id', $schoolId)
            ->where('e.academic_year_id', $academicYearId)
            ->whereNotIn('e.status', [0, 2])
            ->where(static fn (Builder $w) => $w->where('st.full_name', 'ilike', '%'.$like.'%')->orWhere('st.student_code', 'ilike', $like.'%'))
            ->orderBy('st.full_name')
            ->limit($limit)
            ->get(['e.id as enrollment_id', 'e.student_id', 'st.full_name', 'st.student_code', 'sec.name as section_name', 'c.name as class_name'])
            ->map(static fn (object $r): array => (array) $r)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function boardQuery(int $schoolId, int $academicYearId, array $filters): Builder
    {
        $ids = static fn (string $key): array => FilterValues::ids($filters, $key);

        return DB::table('attendance.sessions as s')
            ->where('s.school_id', $schoolId)
            ->where('s.academic_year_id', $academicYearId)
            ->where('s.status', '<>', 3)
            ->when(! empty($filters['date_from']), static fn (Builder $q) => $q->where('s.session_date', '>=', $filters['date_from']))
            ->when(! empty($filters['date_to']), static fn (Builder $q) => $q->where('s.session_date', '<=', $filters['date_to']))
            ->when($ids('teacher_id') !== [], static fn (Builder $q) => $q->whereIn('s.teacher_id', $ids('teacher_id')))
            ->when($ids('subject_id') !== [], static fn (Builder $q) => $q->whereIn('s.subject_id', $ids('subject_id')))
            ->when($ids('section_id') !== [], static fn (Builder $q) => $q->whereIn('s.section_id', $ids('section_id')))
            ->when($ids('lesson_type') !== [], static fn (Builder $q) => $q->whereIn('s.lesson_type', $ids('lesson_type')))
            ->when($ids('origin') !== [], static fn (Builder $q) => $q->whereIn('s.origin', $ids('origin')))
            ->when(! empty($filters['late']), static fn (Builder $q) => $q->where('s.submitted_late', true))
            ->when($ids('branch_id') !== [], static fn (Builder $q) => $q->whereIn('s.section_id', static fn (Builder $sub) => $sub->select('section_id')->from('enrollment.enrollments')->where('academic_year_id', $academicYearId)->whereIn('branch_id', $ids('branch_id'))))
            ->when($ids('department_id') !== [], static fn (Builder $q) => $q->whereIn('s.section_id', static fn (Builder $sub) => $sub->select('section_id')->from('enrollment.enrollments')->where('academic_year_id', $academicYearId)->whereIn('department_id', $ids('department_id'))))
            ->when($ids('class_id') !== [], static fn (Builder $q) => $q->whereIn('s.section_id', static fn (Builder $sub) => $sub->select('id')->from('enrollment.sections')->whereIn('class_id', $ids('class_id'))));
    }
}
