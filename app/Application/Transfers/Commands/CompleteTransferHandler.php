<?php

namespace App\Application\Transfers\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Transfers\Results\CompleteTransferResult;
use App\Application\Transfers\Support\TransferCompletionGuard;
use App\Domain\Enrollment\Data\CreateEnrollmentData;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Transfers\Data\TransferRequestSnapshot;
use App\Domain\Transfers\Events\TransferCompleted;
use App\Domain\Transfers\Repositories\TransferRepositoryInterface;
use App\Domain\Transfers\Support\TransferIdempotencyGuard;

final class CompleteTransferHandler implements CommandHandler
{
    private const COMMAND_NAME = 'CompleteTransfer';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly TransferRepositoryInterface $transfers,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly TransferCompletionGuard $guard,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): CompleteTransferResult
    {
        assert($command instanceof CompleteTransferCommand);
        $key = TransferIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return CompleteTransferResult::fromIdempotency(
                (int) $cached['transfer_request_id'],
                (int) $cached['transfer_record_id'],
                (int) $cached['to_enrollment_id'],
            );
        }

        [$req, $error] = $this->guard->resolveApprovedRequest(
            $command->transferRequestId,
            $command->toSchoolId,
        );
        if ($error !== null || $req === null) {
            return CompleteTransferResult::failure([$error ?? 'transfers.complete_failed']);
        }

        $placementError = $this->guard->destinationPlacementError(
            $command->toClassId,
            $command->toSectionId,
            $command->toSchoolId,
            $req->academicYearId,
        );
        if ($placementError !== null) {
            return CompleteTransferResult::failure([$placementError]);
        }

        $payload = $this->unitOfWork->transaction(
            fn (): ?array => $this->persistCompletion($command, $req, $key),
        );

        if ($payload === null) {
            return CompleteTransferResult::failure(['transfers.complete_failed']);
        }

        return CompleteTransferResult::success(
            $payload['request_id'],
            $payload['record_id'],
            $payload['to_enrollment_id'],
        );
    }

    /**
     * @return array{request_id:int,record_id:int,to_enrollment_id:int}|null
     */
    private function persistCompletion(
        CompleteTransferCommand $command,
        TransferRequestSnapshot $req,
        string $key,
    ): ?array {
        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');

        if (! $this->enrollments->closeAsTransferred(
            $req->fromEnrollmentId,
            $req->fromSchoolId,
            $command->effectiveDate,
        )) {
            return null;
        }

        $toEnrollmentId = $this->enrollments->save(new CreateEnrollmentData(
            studentId: $req->studentId,
            academicYearId: $req->academicYearId,
            schoolId: $command->toSchoolId,
            classId: $command->toClassId,
            sectionId: $command->toSectionId,
            enrollmentNumber: $this->enrollments->generateEnrollmentNumber(
                $command->toSchoolId,
                $req->academicYearId,
            ),
            effectiveFrom: $command->effectiveDate,
            specializationId: $command->specializationId,
            enrolledBy: $command->completedBy,
        ));

        $recordId = $this->transfers->insertTransferRecord(
            $req->id,
            $req->studentId,
            $req->fromSchoolId,
            $req->toSchoolId,
            $req->fromEnrollmentId,
            $toEnrollmentId,
            $command->effectiveDate,
            $at,
            $at,
        );

        if (! $this->transfers->markCompleted($req->id, $command->toSchoolId)) {
            return null;
        }

        $this->transfers->updateStudentCurrentSchool($req->studentId, $command->toSchoolId);
        $this->outbox->stage(new TransferCompleted(
            $req->id,
            $recordId,
            $req->fromSchoolId,
            $req->toSchoolId,
            $req->fromEnrollmentId,
            $toEnrollmentId,
            new \DateTimeImmutable,
        ));
        $this->idempotency->store($key, self::COMMAND_NAME, [
            'transfer_request_id' => $req->id,
            'transfer_record_id' => $recordId,
            'to_enrollment_id' => $toEnrollmentId,
        ]);

        return [
            'request_id' => $req->id,
            'record_id' => $recordId,
            'to_enrollment_id' => $toEnrollmentId,
        ];
    }
}
