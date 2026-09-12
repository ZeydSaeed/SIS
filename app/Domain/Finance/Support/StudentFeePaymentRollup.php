<?php

namespace App\Domain\Finance\Support;

use App\Domain\Finance\ValueObjects\StudentFeeStatus;

final class StudentFeePaymentRollup
{
    public static function statusAfterPayment(string $feeAmount, string $paidTotalIncludingNew): int
    {
        if (bccomp($paidTotalIncludingNew, $feeAmount, 2) >= 0) {
            return StudentFeeStatus::Paid;
        }

        if (bccomp($paidTotalIncludingNew, '0', 2) > 0) {
            return StudentFeeStatus::Partial;
        }

        return StudentFeeStatus::Unpaid;
    }

    public static function remaining(string $feeAmount, string $paidSoFar): string
    {
        $remaining = bcsub($feeAmount, $paidSoFar, 2);

        return bccomp($remaining, '0', 2) < 0 ? '0.00' : $remaining;
    }
}
