<?php

namespace App\Application\Attendance\Commands;

use App\Application\Attendance\Contracts\AttendanceClockInterface;
use App\Application\Attendance\Contracts\CheckinCodeHasherInterface;
use App\Application\Attendance\Results\IssueNfcStationTokenResult;
use App\Application\Attendance\Support\AttendancePolicyResolver;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Attendance\Exceptions\AttendanceRuleViolation;
use App\Domain\Attendance\Repositories\StaffAttendanceRepositoryInterface;

/**
 * NFC is optional (policy). The token is random, hashed at rest, valid for one year (or until a new token replaces
 * it); the plain token is returned once so it can be written to the tag. Tags carry no personal data.
 */
final class IssueNfcStationTokenHandler implements CommandHandler
{
    public const COMMAND_NAME = 'IssueNfcStationToken';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StaffAttendanceRepositoryInterface $staff,
        private readonly CheckinCodeHasherInterface $hasher,
        private readonly AttendancePolicyResolver $policies,
        private readonly AttendanceClockInterface $clock,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): IssueNfcStationTokenResult
    {
        assert($command instanceof IssueNfcStationTokenCommand);

        $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
        if ($cached !== null) {
            return IssueNfcStationTokenResult::fromIdempotency($cached + ['token' => null]);
        }
        if (! $this->policies->current($command->schoolId, $command->academicYearId)->nfcEnabled) {
            throw AttendanceRuleViolation::evidenceDisabled('nfc');
        }

        $now = $this->clock->now();
        $until = $now->modify('+1 year');
        $token = bin2hex(random_bytes(12));
        $label = trim($command->label) === '' ? null : mb_substr(trim($command->label), 0, 100);

        $id = $this->unitOfWork->transaction(function () use ($command, $now, $until, $token, $label): int {
            $this->staff->revokeCodes($command->schoolId, $now, 2);

            return $this->staff->storeCode($command->schoolId, $this->hasher->hash($command->schoolId, $token), $now, $until, $command->actorUserId, 2, $label);
        });

        $public = ['token_id' => $id, 'valid_until' => $until->format(DATE_ATOM), 'label' => $label];
        $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, $public);

        return IssueNfcStationTokenResult::success($public + ['token' => $token]);
    }
}
