<?php

namespace App\Infrastructure\Persistence\Leave;

use App\Application\Leave\Contracts\LeaveReadRepositoryInterface;
use App\Domain\Leave\ValueObjects\LeaveStatus;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read model of the leave module: one joined query per screen (no N+1), named columns only, paged in SQL. Names are
 * read from their owners (teachers / hr / users / leave types) at read time — never copied into leave rows.
 */
final class EloquentLeaveReadRepository implements LeaveReadRepositoryInterface
{
    private const SORTS = [
        'request_no' => 'r.request_no',
        'person' => 'person_name',
        'type' => 'lt.name_ar',
        'from' => 'r.date_from',
        'to' => 'r.date_to',
        'quantity' => 'r.quantity',
        'status' => 'r.status',
        'submitted' => 'r.submitted_at',
    ];

    /**
     * Person × type balances of a year: staff of the school-year (teachers with an active membership, employees not
     * linked to a teacher) × active types whose current policy deducts, with the ledger sums and what open requests hold.
     */
    private const BALANCES_SQL = '
        WITH people AS (
            SELECT 1 AS kind, t.id, t.full_name AS name, t.employee_code AS code FROM teachers.teachers t
            WHERE EXISTS (SELECT 1 FROM teachers.teacher_schools ts WHERE ts.teacher_id = t.id AND ts.school_id = ? AND ts.academic_year_id = ? AND ts.left_at IS NULL)
            UNION ALL
            SELECT 2, e.id, e.full_name, e.employee_number FROM hr.employees e
            WHERE e.teacher_id IS NULL AND e.status = 1
              AND EXISTS (SELECT 1 FROM hr.employee_schools es WHERE es.employee_id = e.id AND es.school_id = ? AND es.academic_year_id = ?)
        ),
        types AS (
            SELECT lt.id, lt.name_ar, lt.unit, lt.is_sensitive, lt.applies_to, p.annual_entitlement
            FROM leave.types lt JOIN leave.policies p ON p.leave_type_id = lt.id AND p.effective_to IS NULL
            WHERE lt.school_id = ? AND lt.status = 1 AND p.deducts_balance
        ),
        led AS (
            SELECT requester_kind AS kind, COALESCE(teacher_id, employee_id) AS pid, leave_type_id,
                   COALESCE(SUM(quantity) FILTER (WHERE entry_type = 1), 0) AS entitled,
                   COALESCE(SUM(quantity) FILTER (WHERE entry_type = 2), 0) AS adjusted,
                   COALESCE(-SUM(quantity) FILTER (WHERE entry_type = 3), 0) - COALESCE(SUM(quantity) FILTER (WHERE entry_type = 4), 0) AS used,
                   BOOL_OR(entry_type = 1) AS granted
            FROM leave.balance_entries WHERE school_id = ? AND academic_year_id = ? GROUP BY 1, 2, 3
        ),
        res AS (
            SELECT r.requester_kind AS kind, COALESCE(r.teacher_id, r.employee_id) AS pid, r.leave_type_id, SUM(r.quantity) AS reserved
            FROM leave.requests r JOIN leave.policies p ON p.id = r.policy_id
            WHERE r.school_id = ? AND r.academic_year_id = ? AND r.status IN (2, 3) AND p.deducts_balance GROUP BY 1, 2, 3
        )
        SELECT pe.kind, pe.id AS person_id, pe.name AS person_name, pe.code AS person_code,
               ty.id AS leave_type_id, ty.name_ar AS type_name, ty.unit, ty.is_sensitive, ty.annual_entitlement,
               COALESCE(led.entitled, 0) AS entitled, COALESCE(led.adjusted, 0) AS adjusted, COALESCE(led.used, 0) AS used,
               COALESCE(res.reserved, 0) AS reserved, COALESCE(led.granted, FALSE) AS granted,
               COUNT(*) OVER () AS total_rows
        FROM people pe
        JOIN types ty ON ty.applies_to = 3 OR ty.applies_to = pe.kind
        LEFT JOIN led ON led.kind = pe.kind AND led.pid = pe.id AND led.leave_type_id = ty.id
        LEFT JOIN res ON res.kind = pe.kind AND res.pid = pe.id AND res.leave_type_id = ty.id
        WHERE (CARDINALITY(CAST(? AS INTEGER[])) = 0 OR pe.kind = ANY (CAST(? AS INTEGER[])))
          AND (CARDINALITY(CAST(? AS BIGINT[])) = 0 OR ty.id = ANY (CAST(? AS BIGINT[])))
          AND (CHAR_LENGTH(?) = 0 OR pe.name ILIKE ? OR pe.code ILIKE ?)
          AND (NOT CAST(? AS BOOLEAN) OR (pe.kind, pe.id) IN (SELECT * FROM UNNEST(CAST(? AS INTEGER[]), CAST(? AS BIGINT[]))))
        ORDER BY pe.name, pe.kind, pe.id, ty.name_ar
        LIMIT ? OFFSET ?';

    public function requests(int $schoolId, array $filters, int $page, int $perPage): array
    {
        $query = $this->filtered($this->base($schoolId), $filters);
        $total = (clone $query)->count();
        $sort = self::SORTS[$filters['sort'] ?? ''] ?? 'r.request_no';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $items = $query->orderBy($sort, $direction)->orderByDesc('r.id')
            ->forPage(max(1, $page), $perPage)
            ->get()->map(fn (object $row): array => $this->row($row))->all();

        return ['items' => $items, 'total' => $total];
    }

    public function request(int $schoolId, int $requestId): ?array
    {
        $row = $this->base($schoolId)->where('r.id', $requestId)->first();

        return $row === null ? null : $this->row($row);
    }

    public function events(int $schoolId, int $requestId): array
    {
        return DB::table('leave.request_events as ev')
            ->leftJoin('public.users as u', 'u.id', '=', 'ev.actor_user_id')
            ->where('ev.school_id', $schoolId)->where('ev.request_id', $requestId)
            ->orderBy('ev.id')
            ->get(['ev.id', 'ev.event_type', 'ev.from_status', 'ev.to_status', 'ev.level', 'ev.note', 'ev.created_at', 'u.name as actor_name'])
            ->map(static fn (object $e): array => [
                'id' => (int) $e->id,
                'event_type' => (int) $e->event_type,
                'from_status' => $e->from_status !== null ? (int) $e->from_status : null,
                'to_status' => (int) $e->to_status,
                'level' => $e->level !== null ? (int) $e->level : null,
                'note' => $e->note,
                'actor_name' => $e->actor_name,
                'created_at' => (string) $e->created_at,
            ])->all();
    }

    public function awaitingCount(int $schoolId, int $userId): int
    {
        return $this->awaitingDecisionOf($this->joined($schoolId), $userId)->count();
    }

    /**
     * «بانتظار موافقتي»: pending requests the user may still decide — not their own leave, and not a request whose
     * current round they already approved a level of (one person never passes two levels).
     */
    private function awaitingDecisionOf(Builder $query, int $userId): Builder
    {
        return $query
            ->where('r.status', LeaveStatus::Pending->value)
            ->whereRaw('COALESCE(t.user_id, e.user_id) IS DISTINCT FROM ?', [$userId])
            ->whereRaw('NOT EXISTS (
                SELECT 1 FROM leave.request_events ev
                WHERE ev.request_id = r.id AND ev.event_type = 4 AND ev.actor_user_id = ?
                  AND ev.id > COALESCE((SELECT MAX(s.id) FROM leave.request_events s WHERE s.request_id = r.id AND s.event_type IN (3, 8)), 0)
            )', [$userId]);
    }

    public function dashboardCounts(int $schoolId, int $academicYearId, string $today, ?int $ownUserId): array
    {
        $soon = (new \DateTimeImmutable($today))->modify('+7 days')->format('Y-m-d');
        $row = $this->own($this->joined($schoolId), $ownUserId)
            ->where('r.academic_year_id', $academicYearId)
            ->selectRaw('
                COUNT(*) FILTER (WHERE r.status = 1) AS drafts,
                COUNT(*) FILTER (WHERE r.status = 2) AS pending,
                COUNT(*) FILTER (WHERE r.status = 3) AS info_required,
                COUNT(*) FILTER (WHERE r.status = 4) AS approved,
                COUNT(*) FILTER (WHERE r.status = 5) AS rejected,
                COUNT(*) FILTER (WHERE r.status IN (6, 7)) AS closed,
                COUNT(*) FILTER (WHERE r.status = 2 AND r.submitted_at >= CAST(? AS date) - 1) AS new_requests,
                COUNT(*) FILTER (WHERE r.status = 4 AND r.date_from <= ? AND r.date_to >= ?) AS away_today,
                COUNT(*) FILTER (WHERE r.status = 4 AND r.date_from <= ? AND r.date_to >= ? AND r.requester_kind = 1) AS teachers_away_today,
                COUNT(*) FILTER (WHERE r.status IN (2, 3, 4) AND r.date_from > ? AND r.date_from <= ?) AS starting_soon
            ', [$today, $today, $today, $today, $today, $today, $soon])
            ->first();

        return array_map(static fn ($v): int => (int) $v, (array) $row);
    }

    public function awayOn(int $schoolId, string $date, ?int $ownUserId): array
    {
        return $this->own($this->base($schoolId), $ownUserId)
            ->where('r.status', LeaveStatus::Approved->value)->where('r.date_from', '<=', $date)->where('r.date_to', '>=', $date)
            ->orderBy('person_name')->limit(200)
            ->get()->map(fn (object $row): array => $this->row($row))->all();
    }

    public function startingBetween(int $schoolId, string $from, string $to, ?int $ownUserId): array
    {
        return $this->own($this->base($schoolId), $ownUserId)
            ->whereIn('r.status', LeaveStatus::activeValues())->whereBetween('r.date_from', [$from, $to])
            ->orderBy('r.date_from')->orderBy('person_name')->limit(200)
            ->get()->map(fn (object $row): array => $this->row($row))->all();
    }

    public function balances(int $schoolId, int $academicYearId, array $filters, int $page, int $perPage): array
    {
        // Fixed SQL text; every filter is a bound parameter (an empty value switches its condition off).
        $people = $filters['people'] ?? null;
        $q = trim((string) ($filters['q'] ?? ''));
        $like = '%'.$this->escapeLike($q).'%';
        $kinds = $this->pgArray($filters['kinds'] ?? []);
        $types = $this->pgArray($filters['type_ids'] ?? []);
        $rows = DB::select(self::BALANCES_SQL, [
            $schoolId, $academicYearId, $schoolId, $academicYearId, $schoolId, $schoolId, $academicYearId, $schoolId, $academicYearId,
            $kinds, $kinds,
            $types, $types,
            $q, $like, $like,
            $people !== null,
            $this->pgArray(array_map(static fn (array $p): int => (int) $p['kind'], $people ?? [])),
            $this->pgArray(array_map(static fn (array $p): int => (int) $p['id'], $people ?? [])),
            $perPage, (max(1, $page) - 1) * $perPage,
        ]);

        return [
            'items' => array_map(static function (object $r): array {
                $balance = round((float) $r->entitled + (float) $r->adjusted - (float) $r->used, 2);

                return [
                    'kind' => (int) $r->kind,
                    'person_id' => (int) $r->person_id,
                    'person_name' => (string) $r->person_name,
                    'person_code' => $r->person_code,
                    'leave_type_id' => (int) $r->leave_type_id,
                    'type_name' => (string) $r->type_name,
                    'unit' => (int) $r->unit,
                    'annual_entitlement' => $r->annual_entitlement !== null ? (float) $r->annual_entitlement : null,
                    'granted' => (bool) $r->granted,
                    'entitled' => (float) $r->entitled,
                    'adjusted' => (float) $r->adjusted,
                    'used' => (float) $r->used,
                    'reserved' => (float) $r->reserved,
                    'balance' => $balance,
                    'available' => round($balance - (float) $r->reserved, 2),
                ];
            }, $rows),
            'total' => $rows === [] ? 0 : (int) $rows[0]->total_rows,
        ];
    }

    public function ledger(int $schoolId, int $academicYearId, int $kind, int $personId, ?int $typeId): array
    {
        return DB::table('leave.balance_entries as b')
            ->join('leave.types as lt', 'lt.id', '=', 'b.leave_type_id')
            ->leftJoin('leave.requests as r', 'r.id', '=', 'b.request_id')
            ->leftJoin('public.users as u', 'u.id', '=', 'b.created_by')
            ->where('b.school_id', $schoolId)->where('b.academic_year_id', $academicYearId)
            ->where($kind === 1 ? 'b.teacher_id' : 'b.employee_id', $personId)
            ->when($typeId !== null, static fn ($q) => $q->where('b.leave_type_id', $typeId))
            ->orderBy('b.id')
            ->get(['b.id', 'b.leave_type_id', 'lt.name_ar as type_name', 'b.entry_type', 'b.quantity', 'b.reason', 'r.request_no', 'b.request_id', 'u.name as created_by_name', 'b.created_at'])
            ->map(static fn (object $e): array => [
                'id' => (int) $e->id,
                'leave_type_id' => (int) $e->leave_type_id,
                'type_name' => (string) $e->type_name,
                'entry_type' => (int) $e->entry_type,
                'quantity' => (float) $e->quantity,
                'reason' => $e->reason,
                'request_id' => $e->request_id !== null ? (int) $e->request_id : null,
                'request_no' => $e->request_no !== null ? (int) $e->request_no : null,
                'created_by_name' => $e->created_by_name,
                'created_at' => (string) $e->created_at,
            ])->all();
    }

    public function calendar(int $schoolId, string $from, string $to, array $filters, ?int $ownUserId): array
    {
        $statuses = array_values(array_intersect($filters['statuses'] ?? LeaveStatus::activeValues(), LeaveStatus::activeValues()));

        return $this->own($this->base($schoolId), $ownUserId)
            ->whereIn('r.status', $statuses === [] ? LeaveStatus::activeValues() : $statuses)
            ->where('r.date_from', '<=', $to)->where('r.date_to', '>=', $from)
            ->when(($filters['kinds'] ?? []) !== [], static fn ($q) => $q->whereIn('r.requester_kind', $filters['kinds']))
            ->when(($filters['type_ids'] ?? []) !== [], static fn ($q) => $q->whereIn('r.leave_type_id', $filters['type_ids']))
            ->orderBy('r.date_from')->orderBy('person_name')->limit(1000)
            ->get()->map(fn (object $row): array => $this->row($row))->all();
    }

    public function studentExcuses(int $schoolId, string $from, string $to): array
    {
        return DB::table('attendance.excuses as x')
            ->join('students.students as s', 's.id', '=', 'x.student_id')
            ->where('x.school_id', $schoolId)->whereIn('x.status', [1, 2])
            ->where('x.date_from', '<=', $to)->where('x.date_to', '>=', $from)
            ->orderBy('x.date_from')->limit(1000)
            ->get(['x.id', 'x.student_id', 's.full_name', 's.student_code', 'x.date_from', 'x.date_to', 'x.excuse_type', 'x.status'])
            ->map(static fn (object $x): array => [
                'id' => (int) $x->id,
                'student_id' => (int) $x->student_id,
                'full_name' => (string) $x->full_name,
                'student_code' => (string) $x->student_code,
                'date_from' => substr((string) $x->date_from, 0, 10),
                'date_to' => substr((string) $x->date_to, 0, 10),
                'excuse_type' => (int) $x->excuse_type,
                'status' => (int) $x->status,
            ])->all();
    }

    public function report(int $schoolId, int $academicYearId, string $kind, array $filters): array
    {
        $query = $this->joined($schoolId)->where('r.academic_year_id', $academicYearId)
            ->when(($filters['from'] ?? null) !== null, static fn ($q) => $q->where('r.date_to', '>=', $filters['from']))
            ->when(($filters['to'] ?? null) !== null, static fn ($q) => $q->where('r.date_from', '<=', $filters['to']))
            ->when(($filters['kinds'] ?? []) !== [], static fn ($q) => $q->whereIn('r.requester_kind', $filters['kinds']))
            ->when(($filters['type_ids'] ?? []) !== [], static fn ($q) => $q->whereIn('r.leave_type_id', $filters['type_ids']));

        $rows = match ($kind) {
            'by_person' => $query->where('r.status', LeaveStatus::Approved->value)
                ->groupBy('r.requester_kind', DB::raw('COALESCE(r.teacher_id, r.employee_id)'), DB::raw('COALESCE(t.full_name, e.full_name)'), 'lt.id', 'lt.name_ar', 'lt.is_sensitive', 'lt.unit')
                ->orderBy(DB::raw('COALESCE(t.full_name, e.full_name)'))->orderBy('lt.name_ar')
                ->get([DB::raw('r.requester_kind'), DB::raw('COALESCE(r.teacher_id, r.employee_id) AS person_id'), DB::raw('COALESCE(t.full_name, e.full_name) AS person_name'), DB::raw('lt.id AS leave_type_id'), DB::raw('lt.name_ar AS type_name'), DB::raw('lt.is_sensitive'), DB::raw('lt.unit'), DB::raw('COUNT(*) AS requests'), DB::raw('SUM(r.quantity) AS quantity')]),
            'by_type' => $query->whereIn('r.status', [2, 3, 4, 5, 6, 7])
                ->groupBy('lt.id', 'lt.name_ar', 'lt.is_sensitive', 'lt.unit')->orderBy('lt.name_ar')
                ->get([DB::raw('lt.id AS leave_type_id'), DB::raw('lt.name_ar AS type_name'), DB::raw('lt.is_sensitive'), DB::raw('lt.unit'),
                    DB::raw('COUNT(*) FILTER (WHERE r.status IN (2, 3)) AS open'), DB::raw('COUNT(*) FILTER (WHERE r.status = 4) AS approved'),
                    DB::raw('COUNT(*) FILTER (WHERE r.status = 5) AS rejected'), DB::raw('COUNT(*) FILTER (WHERE r.status IN (6, 7)) AS closed'),
                    DB::raw('COALESCE(SUM(r.quantity) FILTER (WHERE r.status = 4), 0) AS approved_quantity')]),
            'pending' => $query->whereIn('r.status', [2, 3])->orderBy('r.submitted_at')
                ->get([DB::raw('r.request_no'), DB::raw('COALESCE(t.full_name, e.full_name) AS person_name'), DB::raw('r.requester_kind'), DB::raw('lt.name_ar AS type_name'), DB::raw('lt.is_sensitive'),
                    DB::raw('r.date_from'), DB::raw('r.date_to'), DB::raw('r.quantity'), DB::raw('r.unit'), DB::raw('r.status'), DB::raw('r.approved_levels'), DB::raw('r.approval_levels'),
                    DB::raw('r.submitted_at'), DB::raw('FLOOR(EXTRACT(EPOCH FROM (NOW() - r.submitted_at)) / 86400) AS age_days')]),
            'approval_time' => $query->whereIn('r.status', [4, 5])->whereNotNull('r.submitted_at')->whereNotNull('r.decided_at')
                ->groupBy('lt.id', 'lt.name_ar', 'lt.is_sensitive')->orderBy('lt.name_ar')
                ->get([DB::raw('lt.id AS leave_type_id'), DB::raw('lt.name_ar AS type_name'), DB::raw('lt.is_sensitive'), DB::raw('COUNT(*) AS decided'),
                    DB::raw('ROUND(AVG(EXTRACT(EPOCH FROM (r.decided_at - r.submitted_at)) / 3600)::numeric, 1) AS average_hours'),
                    DB::raw('ROUND(MAX(EXTRACT(EPOCH FROM (r.decided_at - r.submitted_at)) / 3600)::numeric, 1) AS longest_hours')]),
            default => collect(),
        };

        return $rows->map(static function (object $row): array {
            $out = (array) $row;
            $out['is_sensitive'] = (bool) ($out['is_sensitive'] ?? false);
            foreach (['quantity', 'approved_quantity', 'average_hours', 'longest_hours'] as $key) {
                if (isset($out[$key])) {
                    $out[$key] = (float) $out[$key];
                }
            }
            foreach (['date_from', 'date_to'] as $key) {
                if (isset($out[$key])) {
                    $out[$key] = substr((string) $out[$key], 0, 10);
                }
            }

            return $out;
        })->all();
    }

    private function base(int $schoolId): Builder
    {
        return $this->joined($schoolId)
            ->select([
                'r.id', 'r.request_no', 'r.academic_year_id', 'r.requester_kind', 'r.leave_type_id', 'r.policy_id', 'r.date_from', 'r.date_to',
                'r.quantity', 'r.unit', 'r.reason', 'r.status', 'r.approval_levels', 'r.approved_levels', 'r.created_by', 'r.submitted_at',
                'r.decided_at', 'r.decision_note', 'r.version', 'r.created_at',
                DB::raw('COALESCE(r.teacher_id, r.employee_id) AS person_id'),
                DB::raw('COALESCE(t.full_name, e.full_name) AS person_name'),
                DB::raw('COALESCE(t.employee_code, e.employee_number) AS person_code'),
                DB::raw('COALESCE(t.user_id, e.user_id) AS requester_user_id'),
                'lt.code as type_code', 'lt.name_ar as type_name', 'lt.is_sensitive', 'lt.color_hue',
                'cu.name as created_by_name', 'du.name as decided_by_name',
            ]);
    }

    /** The joins and the school bound, without a column list (aggregate reads choose their own columns). */
    private function joined(int $schoolId): Builder
    {
        return DB::table('leave.requests as r')
            ->join('leave.types as lt', 'lt.id', '=', 'r.leave_type_id')
            ->leftJoin('teachers.teachers as t', 't.id', '=', 'r.teacher_id')
            ->leftJoin('hr.employees as e', 'e.id', '=', 'r.employee_id')
            ->leftJoin('public.users as cu', 'cu.id', '=', 'r.created_by')
            ->leftJoin('public.users as du', 'du.id', '=', 'r.decided_by')
            ->where('r.school_id', $schoolId);
    }

    /** Only the user's own requests: as the person on leave, or as the one who entered them. */
    private function own(Builder $query, ?int $ownUserId): Builder
    {
        return $ownUserId === null ? $query : $query->where(static fn ($q) => $q
            ->where('r.created_by', $ownUserId)
            ->orWhere('t.user_id', $ownUserId)
            ->orWhere('e.user_id', $ownUserId));
    }

    /** @param  array<string, mixed>  $filters */
    private function filtered(Builder $query, array $filters): Builder
    {
        $query = $this->own($query, $filters['own_user_id'] ?? null);
        $q = trim((string) ($filters['q'] ?? ''));

        return $query
            ->when(($filters['year_id'] ?? null) !== null, static fn ($b) => $b->where('r.academic_year_id', (int) $filters['year_id']))
            ->when(($filters['statuses'] ?? []) !== [], static fn ($b) => $b->whereIn('r.status', $filters['statuses']))
            ->when(($filters['kinds'] ?? []) !== [], static fn ($b) => $b->whereIn('r.requester_kind', $filters['kinds']))
            ->when(($filters['type_ids'] ?? []) !== [], static fn ($b) => $b->whereIn('r.leave_type_id', $filters['type_ids']))
            ->when(($filters['from'] ?? null) !== null, static fn ($b) => $b->where('r.date_to', '>=', $filters['from']))
            ->when(($filters['to'] ?? null) !== null, static fn ($b) => $b->where('r.date_from', '<=', $filters['to']))
            ->when(($filters['approver_user_id'] ?? null) !== null, fn ($b) => $this->awaitingDecisionOf($b, (int) $filters['approver_user_id']))
            ->when($q !== '', function ($b) use ($q) {
                $like = '%'.$this->escapeLike($q).'%';

                return $b->where(static fn ($w) => $w
                    ->where('t.full_name', 'ILIKE', $like)->orWhere('e.full_name', 'ILIKE', $like)
                    ->orWhere('t.employee_code', 'ILIKE', $like)->orWhere('e.employee_number', 'ILIKE', $like)
                    ->when(ctype_digit($q), static fn ($n) => $n->orWhere('r.request_no', (int) $q)));
            });
    }

    /** @return array<string, mixed> */
    private function row(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'request_no' => (int) $row->request_no,
            'academic_year_id' => (int) $row->academic_year_id,
            'requester_kind' => (int) $row->requester_kind,
            'person_id' => (int) $row->person_id,
            'person_name' => $row->person_name,
            'person_code' => $row->person_code,
            'requester_user_id' => $row->requester_user_id !== null ? (int) $row->requester_user_id : null,
            'leave_type_id' => (int) $row->leave_type_id,
            'policy_id' => (int) $row->policy_id,
            'type_code' => $row->type_code,
            'type_name' => $row->type_name,
            'is_sensitive' => (bool) $row->is_sensitive,
            'color_hue' => $row->color_hue !== null ? (int) $row->color_hue : null,
            'date_from' => substr((string) $row->date_from, 0, 10),
            'date_to' => substr((string) $row->date_to, 0, 10),
            'quantity' => (float) $row->quantity,
            'unit' => (int) $row->unit,
            'reason' => $row->reason,
            'status' => (int) $row->status,
            'approval_levels' => (int) $row->approval_levels,
            'approved_levels' => (int) $row->approved_levels,
            'created_by' => $row->created_by !== null ? (int) $row->created_by : null,
            'created_by_name' => $row->created_by_name,
            'decided_by_name' => $row->decided_by_name,
            'submitted_at' => $row->submitted_at !== null ? (string) $row->submitted_at : null,
            'decided_at' => $row->decided_at !== null ? (string) $row->decided_at : null,
            'decision_note' => $row->decision_note,
            'version' => (int) $row->version,
            'created_at' => (string) $row->created_at,
        ];
    }

    /** A PostgreSQL array literal of integers (`{1,2,3}`) — bound as one parameter. */
    private function pgArray(array $values): string
    {
        return '{'.implode(',', array_map('intval', $values)).'}';
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
