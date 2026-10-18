<?php

namespace App\Infrastructure\Persistence\Leave;

use App\Domain\Leave\Data\LeaveDraft;
use App\Domain\Leave\Data\LeaveRequester;
use App\Domain\Leave\Data\LeaveRequestSnapshot;
use App\Domain\Leave\Repositories\LeaveRequestRepositoryInterface;
use App\Domain\Leave\ValueObjects\LeaveEventType;
use App\Domain\Leave\ValueObjects\LeaveStatus;
use App\Domain\Leave\ValueObjects\LeaveUnit;
use App\Domain\Leave\ValueObjects\RequesterKind;
use Illuminate\Support\Facades\DB;

final class EloquentLeaveRequestRepository implements LeaveRequestRepositoryInterface
{
    private const COLUMNS = [
        'id', 'school_id', 'academic_year_id', 'request_no', 'requester_kind', 'teacher_id', 'employee_id', 'leave_type_id',
        'policy_id', 'date_from', 'date_to', 'quantity', 'unit', 'reason', 'status', 'approval_levels', 'approved_levels',
        'created_by', 'version',
    ];

    public function find(int $schoolId, int $requestId): ?LeaveRequestSnapshot
    {
        $row = DB::table('leave.requests')->where('school_id', $schoolId)->where('id', $requestId)->first(self::COLUMNS);

        return $row === null ? null : $this->snapshot($row);
    }

    public function lock(int $schoolId, int $requestId): ?LeaveRequestSnapshot
    {
        $row = DB::table('leave.requests')->where('school_id', $schoolId)->where('id', $requestId)->lockForUpdate()->first(self::COLUMNS);

        return $row === null ? null : $this->snapshot($row);
    }

    public function nextNumber(int $schoolId): int
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ['leave.requests.no.'.$schoolId]);

        return (int) (DB::table('leave.requests')->where('school_id', $schoolId)->max('request_no') ?? 0) + 1;
    }

    public function create(int $schoolId, int $academicYearId, int $requestNo, LeaveRequester $requester, int $policyId, LeaveDraft $draft, float $quantity, LeaveUnit $unit, int $approvalLevels, int $createdBy): int
    {
        return (int) DB::table('leave.requests')->insertGetId($this->draftRow($policyId, $draft, $quantity, $unit, $approvalLevels) + [
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'request_no' => $requestNo,
            'requester_kind' => $requester->kind->value,
            'teacher_id' => $requester->teacherId(),
            'employee_id' => $requester->employeeId(),
            'status' => LeaveStatus::Draft->value,
            'created_by' => $createdBy,
        ]);
    }

    public function updateDraft(int $requestId, int $version, int $policyId, LeaveDraft $draft, float $quantity, LeaveUnit $unit, int $approvalLevels): bool
    {
        return DB::table('leave.requests')->where('id', $requestId)->where('version', $version)->update(
            $this->draftRow($policyId, $draft, $quantity, $unit, $approvalLevels) + ['version' => $version + 1, 'updated_at' => now()],
        ) === 1;
    }

    public function transition(int $requestId, int $version, LeaveStatus $to, int $approvedLevels, ?int $decidedBy, ?string $note, bool $submitted = false): bool
    {
        $values = [
            'status' => $to->value,
            'approved_levels' => $approvedLevels,
            'decided_by' => $decidedBy,
            'decided_at' => $decidedBy === null ? null : now(),
            'decision_note' => $note,
            'version' => $version + 1,
            'updated_at' => now(),
        ];
        if ($submitted) {
            $values['submitted_at'] = now();
        }

        return DB::table('leave.requests')->where('id', $requestId)->where('version', $version)->update($values) === 1;
    }

    public function lockPerson(int $schoolId, RequesterKind $kind, int $personId): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["leave.person.{$schoolId}.{$kind->value}.{$personId}"]);
    }

    public function overlaps(int $schoolId, RequesterKind $kind, int $personId, string $from, string $to, ?int $exceptId): bool
    {
        return DB::table('leave.requests')
            ->where('school_id', $schoolId)
            ->where($kind === RequesterKind::Teacher ? 'teacher_id' : 'employee_id', $personId)
            ->whereIn('status', LeaveStatus::activeValues())
            ->where('date_from', '<=', $to)
            ->where('date_to', '>=', $from)
            ->when($exceptId !== null, static fn ($q) => $q->where('id', '<>', $exceptId))
            ->exists();
    }

    public function addEvent(int $schoolId, int $requestId, LeaveEventType $type, ?LeaveStatus $from, LeaveStatus $to, ?int $level, ?int $actorUserId, ?string $note, ?array $payload = null): void
    {
        DB::table('leave.request_events')->insert([
            'school_id' => $schoolId,
            'request_id' => $requestId,
            'event_type' => $type->value,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'level' => $level,
            'actor_user_id' => $actorUserId,
            'note' => $note,
            'payload' => $payload === null ? null : json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
    }

    public function currentApprovers(int $requestId): array
    {
        $since = DB::table('leave.request_events')
            ->where('request_id', $requestId)
            ->whereIn('event_type', [LeaveEventType::Submitted->value, LeaveEventType::Resubmitted->value])
            ->max('id');

        return DB::table('leave.request_events')
            ->where('request_id', $requestId)
            ->where('event_type', LeaveEventType::LevelApproved->value)
            ->when($since !== null, static fn ($q) => $q->where('id', '>', $since))
            ->pluck('actor_user_id')
            ->filter()
            ->map(static fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    private function draftRow(int $policyId, LeaveDraft $draft, float $quantity, LeaveUnit $unit, int $approvalLevels): array
    {
        return [
            'leave_type_id' => $draft->leaveTypeId,
            'policy_id' => $policyId,
            'date_from' => $draft->dateFrom,
            'date_to' => $draft->dateTo,
            'quantity' => $quantity,
            'unit' => $unit->value,
            'reason' => trim($draft->reason),
            'approval_levels' => $approvalLevels,
        ];
    }

    private function snapshot(object $row): LeaveRequestSnapshot
    {
        $kind = RequesterKind::from((int) $row->requester_kind);

        return new LeaveRequestSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            academicYearId: (int) $row->academic_year_id,
            requestNo: (int) $row->request_no,
            kind: $kind,
            personId: (int) ($kind === RequesterKind::Teacher ? $row->teacher_id : $row->employee_id),
            leaveTypeId: (int) $row->leave_type_id,
            policyId: (int) $row->policy_id,
            dateFrom: substr((string) $row->date_from, 0, 10),
            dateTo: substr((string) $row->date_to, 0, 10),
            quantity: (float) $row->quantity,
            unit: LeaveUnit::from((int) $row->unit),
            reason: (string) $row->reason,
            status: LeaveStatus::from((int) $row->status),
            approvalLevels: (int) $row->approval_levels,
            approvedLevels: (int) $row->approved_levels,
            createdBy: $row->created_by !== null ? (int) $row->created_by : null,
            version: (int) $row->version,
        );
    }
}
