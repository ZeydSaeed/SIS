<?php

namespace App\Domain\Leave\Data;

use App\Domain\Leave\ValueObjects\LeaveAudience;
use App\Domain\Leave\ValueObjects\LeaveUnit;

/** A school's leave type with its current policy version (null only for a type created without one — never). */
final readonly class LeaveTypeSnapshot
{
    public const ACTIVE = 1;

    public function __construct(
        public int $id,
        public int $schoolId,
        public string $code,
        public string $nameAr,
        public ?string $nameEn,
        public ?string $description,
        public LeaveAudience $audience,
        public LeaveUnit $unit,
        public bool $isSensitive,
        public ?int $colorHue,
        public int $status,
        public ?LeavePolicySnapshot $policy,
    ) {}

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }
}
