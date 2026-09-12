<?php

namespace App\Application\Finance\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Finance\Results\AssignStudentFeeResult;
use App\Domain\Finance\Events\StudentFeeAssigned;
use App\Domain\Finance\Repositories\StudentFeeRepositoryInterface;
use App\Domain\Finance\Support\FinanceIdempotencyGuard;
use App\Domain\Finance\Support\FinanceLedgerAppender;
use App\Domain\Finance\ValueObjects\StudentFeeStatus;

final class AssignStudentFeeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'AssignStudentFee';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentFeeRepositoryInterface $studentFees,
        private readonly FinanceLedgerAppender $ledger,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): AssignStudentFeeResult
    {
        assert($command instanceof AssignStudentFeeCommand);
        $key = FinanceIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return AssignStudentFeeResult::fromIdempotency((int) $cached['student_fee_id']);
        }

        if (! $this->studentFees->academicYearExists($command->academicYearId)) {
            return AssignStudentFeeResult::failure(['finance.academic_year_not_found']);
        }
        if (! $this->studentFees->enrollmentBelongsToSchoolYear(
            $command->enrollmentId,
            $command->schoolId,
            $command->academicYearId,
        )) {
            return AssignStudentFeeResult::failure(['finance.enrollment_not_found']);
        }

        $studentId = $this->studentFees->findStudentIdByEnrollment($command->schoolId, $command->enrollmentId);
        if ($studentId === null) {
            return AssignStudentFeeResult::failure(['finance.enrollment_not_found']);
        }

        $catalogAmount = $this->studentFees->findActiveFeeTypeAmount($command->schoolId, $command->feeTypeId);
        if ($catalogAmount === null) {
            return AssignStudentFeeResult::failure(['finance.fee_type_not_found']);
        }

        $amount = $command->amountOverride ?? $catalogAmount;
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount) || bccomp($amount, '0', 2) < 0) {
            return AssignStudentFeeResult::failure(['finance.student_fee_amount_invalid']);
        }

        if ($this->studentFees->findExistingId(
            $command->schoolId,
            $command->enrollmentId,
            $command->feeTypeId,
            $command->academicYearId,
        ) !== null) {
            return AssignStudentFeeResult::failure(['finance.student_fee_duplicate']);
        }

        $dueDate = $command->dueDate !== null && trim($command->dueDate) !== ''
            ? trim($command->dueDate)
            : null;

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');
        $id = $this->unitOfWork->transaction(function () use ($command, $key, $amount, $dueDate, $at, $studentId): int {
            $id = $this->studentFees->create(
                $command->schoolId,
                $command->enrollmentId,
                $command->feeTypeId,
                $command->academicYearId,
                $amount,
                $dueDate,
                StudentFeeStatus::Unpaid,
                $at,
            );
            $this->ledger->appendFeeAssigned(
                $command->schoolId,
                $studentId,
                $command->academicYearId,
                $amount,
                $id,
                null,
                $at,
            );
            $this->outbox->stage(new StudentFeeAssigned(
                $id,
                $command->schoolId,
                $command->enrollmentId,
                $command->feeTypeId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, ['student_fee_id' => $id]);

            return $id;
        });

        return AssignStudentFeeResult::success($id);
    }
}
