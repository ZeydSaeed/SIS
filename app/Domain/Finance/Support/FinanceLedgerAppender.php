<?php

namespace App\Domain\Finance\Support;

use App\Domain\Finance\Repositories\FinanceTransactionRepositoryInterface;
use App\Domain\Finance\ValueObjects\FinanceTransactionType;

final class FinanceLedgerAppender
{
    public function __construct(
        private readonly FinanceTransactionRepositoryInterface $transactions,
    ) {}

    public function appendFeeAssigned(
        int $schoolId,
        int $studentId,
        int $academicYearId,
        string $amount,
        int $studentFeeId,
        ?int $createdBy,
        string $createdAt,
    ): int {
        $previous = $this->transactions->latestBalanceAfter($schoolId, $studentId, $academicYearId) ?? '0.00';
        $balanceAfter = bcadd($previous, $amount, 2);

        return $this->transactions->append(
            $schoolId,
            $studentId,
            $academicYearId,
            FinanceTransactionType::FeeAssigned,
            $amount,
            $balanceAfter,
            'student_fee',
            $studentFeeId,
            null,
            $createdBy,
            $createdAt,
        );
    }

    public function appendPaymentReceived(
        int $schoolId,
        int $studentId,
        int $academicYearId,
        string $amount,
        int $paymentId,
        ?int $createdBy,
        string $createdAt,
    ): int {
        $previous = $this->transactions->latestBalanceAfter($schoolId, $studentId, $academicYearId) ?? '0.00';
        $balanceAfter = bcsub($previous, $amount, 2);

        return $this->transactions->append(
            $schoolId,
            $studentId,
            $academicYearId,
            FinanceTransactionType::PaymentReceived,
            $amount,
            $balanceAfter,
            'payment',
            $paymentId,
            null,
            $createdBy,
            $createdAt,
        );
    }

    public function appendPaymentRefunded(
        int $schoolId,
        int $studentId,
        int $academicYearId,
        string $amount,
        int $paymentId,
        ?int $createdBy,
        string $createdAt,
        ?string $notes = null,
    ): int {
        $previous = $this->transactions->latestBalanceAfter($schoolId, $studentId, $academicYearId) ?? '0.00';
        $balanceAfter = bcadd($previous, $amount, 2);

        return $this->transactions->append(
            $schoolId,
            $studentId,
            $academicYearId,
            FinanceTransactionType::PaymentRefunded,
            $amount,
            $balanceAfter,
            'payment',
            $paymentId,
            $notes,
            $createdBy,
            $createdAt,
        );
    }
}
