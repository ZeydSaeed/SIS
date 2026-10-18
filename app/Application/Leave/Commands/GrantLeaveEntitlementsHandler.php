<?php

namespace App\Application\Leave\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Leave\Contracts\LeaveStaffDirectoryInterface;
use App\Application\Leave\Results\GrantLeaveEntitlementsResult;
use App\Application\Leave\Support\LeaveCalendar;
use App\Application\Leave\Support\LeaveRequestPreparer;
use App\Application\Leave\Support\LeaveTransaction;
use App\Domain\Leave\Data\LeaveRequester;
use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\Repositories\LeaveBalanceRepositoryInterface;

final class GrantLeaveEntitlementsHandler implements CommandHandler
{
    public const COMMAND_NAME = 'Leave.GrantLeaveEntitlements';

    public function __construct(
        private readonly LeaveTransaction $tx,
        private readonly LeaveBalanceRepositoryInterface $balances,
        private readonly LeaveStaffDirectoryInterface $staff,
        private readonly LeaveRequestPreparer $prepare,
        private readonly LeaveCalendar $calendar,
    ) {}

    public function handle(Command $command): GrantLeaveEntitlementsResult
    {
        assert($command instanceof GrantLeaveEntitlementsCommand);

        return $this->tx->run(GrantLeaveEntitlementsResult::class, $command->idempotencyKey, self::COMMAND_NAME, function () use ($command): array {
            $this->calendar->year($command->academicYearId);
            $type = $this->prepare->type($command->schoolId, $command->typeId);
            $entitlement = $type->policy?->annualEntitlement;
            if (! $type->isActive() || $entitlement === null) {
                throw LeaveRuleViolation::of('entitlement_not_set');
            }
            $people = array_map(
                static fn (LeaveRequester $r): array => ['kind' => $r->kind, 'person_id' => $r->personId],
                $this->staff->staff($command->schoolId, $command->academicYearId, $type->audience),
            );
            $added = $this->balances->grantEntitlements($command->schoolId, $command->academicYearId, $type->id, $entitlement, $people, $command->actorUserId);

            return [
                'data' => ['type_id' => $type->id, 'granted' => $added, 'eligible' => count($people)],
                'event' => LeaveTransaction::event('type', $type->id, $command->schoolId, 'entitlements_granted', $command->actorUserId, ['granted' => $added, 'academic_year_id' => $command->academicYearId]),
            ];
        });
    }
}
