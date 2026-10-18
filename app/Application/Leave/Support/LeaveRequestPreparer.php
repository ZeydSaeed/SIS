<?php

namespace App\Application\Leave\Support;

use App\Application\Leave\Contracts\LeaveStaffDirectoryInterface;
use App\Domain\Leave\Data\LeaveDraft;
use App\Domain\Leave\Data\LeaveRequester;
use App\Domain\Leave\Data\LeaveRequestSnapshot;
use App\Domain\Leave\Data\LeaveTypeSnapshot;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveTypeRepositoryInterface;
use App\Domain\Leave\Services\LeaveRequestRules;
use App\Domain\Leave\ValueObjects\RequesterKind;

/**
 * What saving and submitting share: the staff member (working in the school that year), who may act for them
 * (themselves, or a holder of «إدارة طلبات الإجازة»), the type with its current policy, and the quantity counted
 * against the school calendar.
 */
final class LeaveRequestPreparer
{
    public function __construct(
        private readonly LeaveStaffDirectoryInterface $staff,
        private readonly LeaveTypeRepositoryInterface $types,
        private readonly LeaveCalendar $calendar,
    ) {}

    public function requester(int $schoolId, int $academicYearId, RequesterKind $kind, int $personId): LeaveRequester
    {
        return $this->staff->requester($schoolId, $academicYearId, $kind, $personId)
            ?? throw LeaveRuleViolation::of('requester_not_in_school');
    }

    /** Own requests need no manage right; anyone else's do (the HTTP layer already checked the permission itself). */
    public function assertMayActFor(LeaveRequester $requester, int $actorUserId, bool $canManage): void
    {
        if (! $canManage && ! $requester->isUser($actorUserId)) {
            throw LeaveRuleViolation::of('not_own_request');
        }
    }

    /** The person of an existing request — kept even if they have since left the school. */
    public function personOf(LeaveRequestSnapshot $request): LeaveRequester
    {
        return $this->staff->person($request->kind, $request->personId)
            ?? new LeaveRequester($request->kind, $request->personId, null, '');
    }

    public function type(int $schoolId, int $typeId): LeaveTypeSnapshot
    {
        return $this->types->find($schoolId, $typeId) ?? throw LeaveRuleViolation::typeNotFound();
    }

    public function quantity(int $schoolId, int $academicYearId, LeaveTypeSnapshot $type, RequesterKind $kind, LeaveDraft $draft): float
    {
        $year = $this->calendar->year($academicYearId);

        return LeaveRequestRules::quantity(
            $type,
            $kind,
            $draft,
            $year->startDate,
            $year->endDate,
            $this->calendar->workingWeekdays($schoolId, $academicYearId),
            $this->calendar->holidayDates($schoolId, $academicYearId),
        );
    }

    public static function draftOf(LeaveRequestSnapshot $request): LeaveDraft
    {
        return new LeaveDraft(
            $request->leaveTypeId,
            $request->dateFrom,
            $request->dateTo,
            $request->unit->value === 2 ? $request->quantity : null,
            $request->reason,
        );
    }
}
