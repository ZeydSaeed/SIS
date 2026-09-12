<?php

namespace App\Application\Transfers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Transfers\Contracts\TransferApprovalHookPort;
use App\Application\Transfers\Results\CreateTransferRequestResult;
use App\Domain\Transfers\Events\TransferRequestCreated;
use App\Domain\Transfers\Repositories\TransferRepositoryInterface;
use App\Domain\Transfers\Support\TransferIdempotencyGuard;
use App\Domain\Transfers\ValueObjects\TransferRequestStatus;

final class CreateTransferRequestHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CreateTransferRequest';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TransferRepositoryInterface $transfers,
        private readonly TransferApprovalHookPort $approvalHook,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CreateTransferRequestResult
    {
        assert($command instanceof CreateTransferRequestCommand);
        $key = TransferIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CreateTransferRequestResult::fromIdempotency((int) $cached['transfer_request_id']);
        }

        if ($command->fromSchoolId === $command->toSchoolId) {
            return CreateTransferRequestResult::failure(['transfers.schools_must_differ']);
        }
        if (! $this->transfers->schoolExists($command->toSchoolId)) {
            return CreateTransferRequestResult::failure(['transfers.to_school_not_found']);
        }

        $enrollment = $this->transfers->findEnrollmentContext($command->fromEnrollmentId);
        if ($enrollment === null
            || $enrollment['school_id'] !== $command->fromSchoolId
            || $enrollment['academic_year_id'] !== $command->academicYearId) {
            return CreateTransferRequestResult::failure(['transfers.enrollment_not_found']);
        }

        $pending = $this->transfers->findPendingRequestIdForEnrollment(
            $command->fromSchoolId,
            $command->fromEnrollmentId,
        );
        if ($pending !== null) {
            return CreateTransferRequestResult::failure(['transfers.pending_exists']);
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $enrollment, $at): int {
            $id = $this->transfers->createRequest(
                $enrollment['student_id'],
                $command->fromSchoolId,
                $command->toSchoolId,
                $command->fromEnrollmentId,
                $command->academicYearId,
                $command->reason !== null ? trim($command->reason) : null,
                TransferRequestStatus::Pending,
                $command->requestedBy,
                $at,
                $at,
            );
            $this->outbox->stage(new TransferRequestCreated(
                $id,
                $command->fromSchoolId,
                $command->toSchoolId,
                $enrollment['student_id'],
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['transfer_request_id' => $id]);
            $this->approvalHook->openForNewTransferRequest(
                $command->fromSchoolId,
                $id,
                $command->requestedBy,
            );

            return $id;
        });

        return CreateTransferRequestResult::success($id);
    }
}
