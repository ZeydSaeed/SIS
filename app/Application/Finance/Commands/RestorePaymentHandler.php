<?php

namespace App\Application\Finance\Commands;

use App\Application\Contracts\Command;
use App\Application\Contracts\CommandHandler;
use App\Application\Contracts\IdempotencyStore;
use App\Application\Contracts\OutboxRepository;
use App\Application\Contracts\UnitOfWork;
use App\Application\Finance\Results\RestorePaymentResult;
use App\Domain\Finance\Events\PaymentRestored;
use App\Domain\Finance\Repositories\PaymentRepositoryInterface;
use App\Domain\Finance\Repositories\StudentFeeRepositoryInterface;
use App\Domain\Finance\Support\FinanceIdempotencyGuard;
use App\Domain\Finance\Support\FinanceLedgerAppender;
use App\Domain\Finance\Support\StudentFeePaymentRollup;
use App\Domain\Finance\ValueObjects\PaymentStatus;

final class RestorePaymentHandler implements CommandHandler
{
    private const COMMAND_NAME = 'RestorePayment';

    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly StudentFeeRepositoryInterface $studentFees,
        private readonly PaymentRepositoryInterface $payments,
        private readonly FinanceLedgerAppender $ledger,
        private readonly OutboxRepository $outbox,
        private readonly IdempotencyStore $idempotency,
    ) {}

    public function handle(Command $command): RestorePaymentResult
    {
        assert($command instanceof RestorePaymentCommand);
        $key = FinanceIdempotencyGuard::requireKey($command->idempotencyKey);
        $cached = $this->idempotency->find($key, self::COMMAND_NAME);
        if ($cached !== null) {
            return RestorePaymentResult::fromIdempotency(
                (int) $cached['payment_id'],
                (int) $cached['student_fee_status'],
            );
        }

        $payment = $this->payments->findByIdForSchool($command->schoolId, $command->paymentId);
        if ($payment === null) {
            return RestorePaymentResult::failure(['finance.payment_not_found']);
        }
        if ($payment->status !== PaymentStatus::Voided) {
            return RestorePaymentResult::failure(['finance.payment_not_voided']);
        }

        $fee = $this->studentFees->findById($command->schoolId, $payment->studentFeeId);
        if ($fee === null) {
            return RestorePaymentResult::failure(['finance.student_fee_not_found']);
        }

        $studentId = $this->studentFees->findStudentIdByEnrollment($command->schoolId, $fee->enrollmentId);
        if ($studentId === null) {
            return RestorePaymentResult::failure(['finance.enrollment_not_found']);
        }

        $notes = $command->notes !== null ? trim($command->notes) : null;
        if ($notes === '') {
            $notes = null;
        }

        $at = (new \DateTimeImmutable)->format('Y-m-d H:i:s');

        $newStatus = $this->unitOfWork->transaction(function () use ($command, $key, $payment, $fee, $studentId, $notes, $at): ?int {
            $restored = $this->payments->restorePayment($command->schoolId, $command->paymentId);
            if (! $restored) {
                return null;
            }

            $postedTotal = $this->payments->sumByStudentFee($command->schoolId, $payment->studentFeeId);
            $newStatus = StudentFeePaymentRollup::statusAfterPayment($fee->amount, $postedTotal);
            $this->studentFees->updateStatus($command->schoolId, $payment->studentFeeId, $newStatus);

            $this->ledger->appendPaymentReceived(
                $command->schoolId,
                $studentId,
                $fee->academicYearId,
                $payment->amount,
                $command->paymentId,
                $command->restoredBy,
                $at,
            );

            $this->outbox->stage(new PaymentRestored(
                $command->paymentId,
                $command->schoolId,
                $payment->studentFeeId,
                $payment->amount,
                new \DateTimeImmutable,
            ));

            $this->idempotency->store($key, self::COMMAND_NAME, [
                'payment_id' => $command->paymentId,
                'student_fee_status' => $newStatus,
            ]);

            return $newStatus;
        });

        if ($newStatus === null) {
            return RestorePaymentResult::failure(['finance.payment_restore_failed']);
        }

        return RestorePaymentResult::success($command->paymentId, $newStatus);
    }
}
