<?php

namespace App\Application\Finance\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Finance\Results\RecordPaymentResult;
use App\Domain\Finance\Events\PaymentRecorded;
use App\Domain\Finance\Repositories\PaymentRepositoryInterface;
use App\Domain\Finance\Repositories\StudentFeeRepositoryInterface;
use App\Domain\Finance\Support\FinanceIdempotencyGuard;
use App\Domain\Finance\Support\FinanceLedgerAppender;
use App\Domain\Finance\Support\PaymentRecordingRules;
use App\Domain\Finance\Support\StudentFeePaymentRollup;

final class RecordPaymentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RecordPayment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentFeeRepositoryInterface $studentFees,
        private readonly PaymentRepositoryInterface $payments,
        private readonly FinanceLedgerAppender $ledger,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RecordPaymentResult
    {
        assert($command instanceof RecordPaymentCommand);
        $key = FinanceIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return RecordPaymentResult::fromIdempotency(
                (int) $cached['payment_id'],
                (int) $cached['student_fee_status'],
            );
        }

        $requestErrors = PaymentRecordingRules::validateRequest($command->amount, $command->paymentMethod);
        if ($requestErrors !== []) {
            return RecordPaymentResult::failure($requestErrors);
        }

        $fee = $this->studentFees->findById($command->schoolId, $command->studentFeeId);
        $feeErrors = PaymentRecordingRules::validateFee($fee);
        if ($feeErrors !== []) {
            return RecordPaymentResult::failure($feeErrors);
        }

        $studentId = $this->studentFees->findStudentIdByEnrollment($command->schoolId, $fee->enrollmentId);
        if ($studentId === null) {
            return RecordPaymentResult::failure(['finance.enrollment_not_found']);
        }

        $paidSoFar = $this->payments->sumByStudentFee($command->schoolId, $command->studentFeeId);
        $remaining = StudentFeePaymentRollup::remaining($fee->amount, $paidSoFar);
        $amountErrors = PaymentRecordingRules::validateAmountAgainstRemaining($command->amount, $remaining);
        if ($amountErrors !== []) {
            return RecordPaymentResult::failure($amountErrors);
        }

        $paidAt = PaymentRecordingRules::normalizePaidAt($command->paidAt);
        $reference = PaymentRecordingRules::normalizeReference($command->paymentReference);

        $newTotal = bcadd($paidSoFar, $command->amount, 2);
        $newStatus = StudentFeePaymentRollup::statusAfterPayment($fee->amount, $newTotal);
        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');

        $paymentId = $this->unitOfWork->transaction(function () use ($command, $key, $reference, $paidAt, $newStatus, $at, $fee, $studentId): int {
            $paymentId = $this->payments->create(
                $command->schoolId,
                $command->studentFeeId,
                $command->amount,
                $command->paymentMethod,
                $reference,
                $key,
                $paidAt,
                $command->receivedBy,
                $at,
            );
            $this->studentFees->updateStatus($command->schoolId, $command->studentFeeId, $newStatus);
            $this->ledger->appendPaymentReceived(
                $command->schoolId,
                $studentId,
                $fee->academicYearId,
                $command->amount,
                $paymentId,
                $command->receivedBy,
                $at,
            );
            $this->outbox->stage(new PaymentRecorded(
                $paymentId,
                $command->schoolId,
                $command->studentFeeId,
                $command->amount,
                new \DateTimeImmutable,
            ));
            $this->idempotency->store($key, self::COMMAND_NAME, [
                'payment_id' => $paymentId,
                'student_fee_status' => $newStatus,
            ]);

            return $paymentId;
        });

        return RecordPaymentResult::success($paymentId, $newStatus);
    }
}
