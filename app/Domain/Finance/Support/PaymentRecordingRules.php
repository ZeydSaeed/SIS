<?php

namespace App\Domain\Finance\Support;

use App\Domain\Finance\Data\StudentFeeSnapshot;
use App\Domain\Finance\ValueObjects\PaymentMethod;
use App\Domain\Finance\ValueObjects\StudentFeeStatus;

final class PaymentRecordingRules
{
    /**
     * @return list<string>
     */
    public static function validateRequest(string $amount, int $paymentMethod): array
    {
        if (! PaymentMethod::isValid($paymentMethod)) {
            return ['finance.payment_method_invalid'];
        }
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $amount) || bccomp($amount, '0', 2) <= 0) {
            return ['finance.payment_amount_invalid'];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public static function validateFee(?StudentFeeSnapshot $fee): array
    {
        if ($fee === null) {
            return ['finance.student_fee_not_found'];
        }
        if ($fee->status === StudentFeeStatus::Cancelled) {
            return ['finance.student_fee_cancelled'];
        }
        if ($fee->status === StudentFeeStatus::Paid) {
            return ['finance.student_fee_already_paid'];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    public static function validateAmountAgainstRemaining(string $amount, string $remaining): array
    {
        if (bccomp($amount, $remaining, 2) > 0) {
            return ['finance.payment_exceeds_remaining'];
        }

        return [];
    }

    public static function normalizePaidAt(?string $paidAt): string
    {
        $trimmed = $paidAt !== null ? trim($paidAt) : '';

        return $trimmed !== '' ? $trimmed : (new \DateTimeImmutable)->format('Y-m-d H:i:s');
    }

    public static function normalizeReference(?string $reference): ?string
    {
        if ($reference === null) {
            return null;
        }

        $trimmed = trim($reference);

        return $trimmed === '' ? null : $trimmed;
    }
}
