<?php

namespace App\Application\Admission\Commands;

use App\Application\Admission\Results\TransferApplicationResult;
use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Domain\Admission\Data\TransferApplicationData;
use App\Domain\Admission\Events\ApplicationTransferred;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\Services\TransferApplicationGuard;

/**
 * Transfers page: change an application's school, request kind or academic year (period).
 * These fields are locked everywhere else.
 */
final class TransferApplicationHandler implements CommandHandler
{
    private const COMMAND_NAME = 'TransferApplication';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly AdmissionRepositoryInterface $admission,
        private readonly TransferApplicationGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): TransferApplicationResult
    {
        assert($command instanceof TransferApplicationCommand);

        if ($command->idempotencyKey !== null) {
            $cached = $this->idempotency->find($command->idempotencyKey, self::COMMAND_NAME);
            if ($cached !== null) {
                return TransferApplicationResult::fromIdempotency((int) $cached['application_id']);
            }
        }

        $check = $this->guard->check(
            $command->applicationId,
            $command->schoolId,
            $command->toSchoolId,
            $command->toRequestKind,
            $command->toPeriodId,
            $command->allowedSchoolIds,
        );
        if ($check['code'] !== null || $check['application'] === null || $check['period'] === null) {
            return TransferApplicationResult::failure([$check['code'] ?? 'admission.transfer_failed']);
        }
        $application = $check['application'];
        $period = $check['period'];

        $this->unitOfWork->transaction(function () use ($command, $application, $period): void {
            $this->admission->transferApplication(new TransferApplicationData(
                applicationId: $command->applicationId,
                fromSchoolId: $command->schoolId,
                toSchoolId: $command->toSchoolId,
                fromRequestKind: (int) $application['request_kind'],
                toRequestKind: $command->toRequestKind,
                fromPeriodId: (int) $application['application_period_id'],
                toPeriodId: $command->toPeriodId,
                fromAcademicYearId: (int) $application['academic_year_id'],
                toAcademicYearId: (int) $period['academic_year_id'],
                reason: $command->reason,
                transferredBy: $command->transferredBy,
            ));

            $this->outbox->stage(new ApplicationTransferred(
                applicationId: $command->applicationId,
                fromSchoolId: $command->schoolId,
                toSchoolId: $command->toSchoolId,
                toRequestKind: $command->toRequestKind,
                toPeriodId: $command->toPeriodId,
                occurredAt: new \DateTimeImmutable,
            ));

            if ($command->idempotencyKey !== null) {
                $this->idempotency->store($command->idempotencyKey, self::COMMAND_NAME, [
                    'application_id' => $command->applicationId,
                ]);
            }
        });

        return TransferApplicationResult::success($command->applicationId);
    }
}
