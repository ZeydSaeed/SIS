<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Contracts\CheckinCodeHasherInterface;
use App\Application\Attendance\Contracts\CheckinQrRendererInterface;
use App\Application\Attendance\Results\IssueStaffCheckinCodeResult;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Repositories\StaffAttendanceRepositoryInterface;

/**
 * Only the keyed hash is stored; the plain code (and its QR, when the policy enables QR) is returned once to the
 * issuer for the school's attendance screen and is never cached — a replay reports «already issued» without it.
 */
final class IssueStaffCheckinCodeHandler implements CommandHandler
{
    public const COMMAND_NAME = 'IssueStaffCheckinCode';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StaffAttendanceRepositoryInterface $staff,
        private readonly CheckinCodeHasherInterface $hasher,
        private readonly CheckinQrRendererInterface $qr,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): IssueStaffCheckinCodeResult
    {
        assert($command instanceof IssueStaffCheckinCodeCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return IssueStaffCheckinCodeResult::fromIdempotency($cached + ['code' => null, 'qr_svg' => null]);
        }

        $now = $this->clock->now();
        $policy = $this->policies->current($command->schoolId, $command->academicYearId);
        $until = $now->modify("+{$policy->checkinCodeTtlMinutes} minutes");
        $code = $this->hasher->generate();

        $id = $this->unitOfWork->transaction(function () use ($command, $now, $until, $code): int {
            $this->staff->revokeCodes($command->schoolId, $now);

            return $this->staff->storeCode($command->schoolId, $this->hasher->hash($command->schoolId, $code), $now, $until, $command->actorUserId);
        });

        $public = ['code_id' => $id, 'valid_until' => $until->format(DATE_ATOM)];
        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $public);

        return IssueStaffCheckinCodeResult::success($public + ['code' => $code, 'qr_svg' => $policy->qrEnabled ? $this->qr->svgForCode($code) : null]);
    }
}
