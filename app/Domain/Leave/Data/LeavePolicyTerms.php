<?php

namespace App\Domain\Leave\Data;

use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\ValueObjects\DayBasis;

/**
 * The rules of a policy version as entered. No legal entitlement is assumed: the annual entitlement and the
 * per-request limit stay empty until the institution sets them.
 */
final readonly class LeavePolicyTerms
{
    public const MAX_QUANTITY = 365.0;

    public function __construct(
        public DayBasis $dayBasis,
        public bool $requiresDocument,
        public bool $deductsBalance,
        public int $approvalLevels,
        public ?float $maxPerRequest,
        public ?float $annualEntitlement,
    ) {}

    public function assertValid(): void
    {
        if ($this->approvalLevels < 1 || $this->approvalLevels > 3) {
            throw LeaveRuleViolation::of('policy_levels_invalid');
        }
        foreach ([$this->maxPerRequest, $this->annualEntitlement] as $value) {
            if ($value !== null && ($value < 0 || $value > self::MAX_QUANTITY || fmod($value * 4, 1.0) !== 0.0)) {
                throw LeaveRuleViolation::of('policy_quantity_invalid');
            }
        }
        if ($this->maxPerRequest !== null && $this->maxPerRequest <= 0) {
            throw LeaveRuleViolation::of('policy_quantity_invalid');
        }
    }
}
