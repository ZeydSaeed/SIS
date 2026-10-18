<?php

namespace App\Domain\Leave\Data;

use App\Domain\Leave\ValueObjects\DayBasis;

/**
 * One version of a leave type's rules. Versions are never edited: a change publishes a new version, and every
 * request keeps the id of the version it was submitted under (history explains past decisions).
 */
final readonly class LeavePolicySnapshot
{
    public function __construct(
        public int $id,
        public int $leaveTypeId,
        public int $versionNo,
        public DayBasis $dayBasis,
        public bool $requiresDocument,
        public bool $deductsBalance,
        public int $approvalLevels,
        public ?float $maxPerRequest,
        public ?float $annualEntitlement,
        public string $effectiveFrom,
        public ?string $effectiveTo,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'version_no' => $this->versionNo,
            'day_basis' => $this->dayBasis->value,
            'requires_document' => $this->requiresDocument,
            'deducts_balance' => $this->deductsBalance,
            'approval_levels' => $this->approvalLevels,
            'max_per_request' => $this->maxPerRequest,
            'annual_entitlement' => $this->annualEntitlement,
            'effective_from' => $this->effectiveFrom,
            'effective_to' => $this->effectiveTo,
        ];
    }
}
