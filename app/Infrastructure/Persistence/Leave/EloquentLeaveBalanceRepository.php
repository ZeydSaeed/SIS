<?php

namespace App\Infrastructure\Persistence\Leave;

use App\Domain\Leave\Data\LeaveBalance;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;
use App\Domain\Leave\ValueObjects\BalanceEntryType;
use App\Domain\Leave\ValueObjects\LeaveStatus;
use App\Domain\Leave\ValueObjects\RequesterKind;
use Illuminate\Support\Facades\DB;

final class EloquentLeaveBalanceRepository implements LeaveBalanceRepositoryInterface
{
    public function lockPerson(int $schoolId, int $academicYearId, int $typeId, RequesterKind $kind, int $personId): void
    {
        DB::select('SELECT pg_advisory_xact_lock(hashtext(?))', ["leave.balance.{$schoolId}.{$academicYearId}.{$typeId}.{$kind->value}.{$personId}"]);
    }

    public function balance(int $schoolId, int $academicYearId, int $typeId, RequesterKind $kind, int $personId, ?int $exceptRequestId = null): LeaveBalance
    {
        $person = $kind === RequesterKind::Teacher ? 'teacher_id' : 'employee_id';
        $sums = DB::table('leave.balance_entries')
            ->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->where('leave_type_id', $typeId)->where($person, $personId)
            ->selectRaw('
                COALESCE(SUM(quantity) FILTER (WHERE entry_type = 1), 0) AS entitled,
                COALESCE(SUM(quantity) FILTER (WHERE entry_type = 2), 0) AS adjusted,
                COALESCE(-SUM(quantity) FILTER (WHERE entry_type = 3), 0) AS used,
                COALESCE(SUM(quantity) FILTER (WHERE entry_type = 4), 0) AS restored
            ')
            ->first();
        // Open requests hold their quantity back until decided (only types whose policy deducts).
        $reserved = DB::table('leave.requests as r')
            ->join('leave.policies as p', 'p.id', '=', 'r.policy_id')
            ->where('r.school_id', $schoolId)->where('r.academic_year_id', $academicYearId)->where('r.leave_type_id', $typeId)->where("r.{$person}", $personId)
            ->whereIn('r.status', [LeaveStatus::Pending->value, LeaveStatus::InfoRequired->value])
            ->where('p.deducts_balance', true)
            ->when($exceptRequestId !== null, static fn ($q) => $q->where('r.id', '<>', $exceptRequestId))
            ->sum('r.quantity');

        return new LeaveBalance((float) $sums->entitled, (float) $sums->adjusted, (float) $sums->used, (float) $sums->restored, (float) $reserved);
    }

    public function addEntry(int $schoolId, int $academicYearId, int $typeId, RequesterKind $kind, int $personId, BalanceEntryType $type, float $quantity, ?int $requestId, ?string $reason, ?int $createdBy): int
    {
        return (int) DB::table('leave.balance_entries')->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'leave_type_id' => $typeId,
            'requester_kind' => $kind->value,
            'teacher_id' => $kind === RequesterKind::Teacher ? $personId : null,
            'employee_id' => $kind === RequesterKind::Employee ? $personId : null,
            'entry_type' => $type->value,
            'quantity' => $quantity,
            'request_id' => $requestId,
            'reason' => $reason,
            'created_by' => $createdBy,
        ]);
    }

    public function hasAdjustmentWithReason(int $schoolId, int $academicYearId, int $typeId, RequesterKind $kind, int $personId, string $reason): bool
    {
        $person = $kind === RequesterKind::Teacher ? 'teacher_id' : 'employee_id';

        return DB::table('leave.balance_entries')
            ->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->where('leave_type_id', $typeId)->where($person, $personId)
            ->where('entry_type', BalanceEntryType::Adjustment->value)->where('reason', $reason)->exists();
    }

    public function hasRequestEntry(int $requestId, BalanceEntryType $type): bool
    {
        return DB::table('leave.balance_entries')->where('request_id', $requestId)->where('entry_type', $type->value)->exists();
    }

    public function deductedFor(int $requestId): ?float
    {
        $quantity = DB::table('leave.balance_entries')->where('request_id', $requestId)->where('entry_type', BalanceEntryType::Deduction->value)->value('quantity');

        return $quantity === null ? null : abs((float) $quantity);
    }

    public function grantEntitlements(int $schoolId, int $academicYearId, int $typeId, float $quantity, array $people, ?int $createdBy): int
    {
        $added = 0;
        foreach ($people as $person) {
            $teacher = $person['kind'] === RequesterKind::Teacher;
            // ON CONFLICT on the partial unique of the person's column: an existing entitlement is left as it is.
            $added += DB::affectingStatement(
                'INSERT INTO leave.balance_entries (school_id, academic_year_id, leave_type_id, requester_kind, teacher_id, employee_id, entry_type, quantity, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON CONFLICT (school_id, academic_year_id, leave_type_id, '.($teacher ? 'teacher_id' : 'employee_id').') WHERE entry_type = 1 AND '.($teacher ? 'teacher_id' : 'employee_id').' IS NOT NULL DO NOTHING',
                [$schoolId, $academicYearId, $typeId, $person['kind']->value, $teacher ? $person['person_id'] : null, $teacher ? null : $person['person_id'], BalanceEntryType::Entitlement->value, $quantity, $createdBy],
            );
        }

        return $added;
    }
}
