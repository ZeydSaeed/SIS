<?php

namespace App\Application\Finance\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Finance\Results\ReopenStudentFeeResult;
use App\Domain\Finance\Events\StudentFeeReopened;
use App\Domain\Finance\Repositories\StudentFeeRepositoryInterface;
use App\Domain\Finance\Support\FinanceIdempotencyGuard;
use App\Domain\Finance\ValueObjects\StudentFeeStatus;

final class ReopenStudentFeeHandler implements CommandHandler
{
    private const COMMAND_NAME = 'ReopenStudentFee';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentFeeRepositoryInterface $studentFees,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): ReopenStudentFeeResult
    {
        assert($command instanceof ReopenStudentFeeCommand);
        $key = FinanceIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return ReopenStudentFeeResult::fromIdempotency((int) $cached['student_fee_id']);
        }

        $fee = $this->studentFees->findById($command->schoolId, $command->studentFeeId);
        if ($fee === null) {
            return ReopenStudentFeeResult::failure(['finance.student_fee_not_found']);
        }
        if ($fee->status === StudentFeeStatus::Unpaid) {
            return ReopenStudentFeeResult::failure(['finance.student_fee_already_open']);
        }
        if ($fee->status !== StudentFeeStatus::Cancelled) {
            return ReopenStudentFeeResult::failure(['finance.student_fee_not_reopenable']);
        }

        $this->unitOfWork->transaction(function () use ($command, $key): void {
            $this->studentFees->updateStatus(
                $command->schoolId,
                $command->studentFeeId,
                StudentFeeStatus::Unpaid,
            );
            $this->outbox->stage(new StudentFeeReopened(
                $command->studentFeeId,
                $command->schoolId,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'student_fee_id' => $command->studentFeeId,
            ]);
        });

        return ReopenStudentFeeResult::success($command->studentFeeId);
    }
}
