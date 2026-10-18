<?php

namespace App\Domain\Leave\Data;

use App\Domain\Leave\Exceptions\LeaveRuleViolation;
use App\Domain\Leave\ValueObjects\LeaveAudience;
use App\Domain\Leave\ValueObjects\LeaveUnit;

/** The descriptive fields of a leave type (its rules live in the versioned policy). */
final readonly class LeaveTypeFields
{
    public const MAX_NAME = 100;

    public function __construct(
        public string $nameAr,
        public ?string $nameEn,
        public ?string $description,
        public LeaveAudience $audience,
        public LeaveUnit $unit,
        public bool $isSensitive,
        public ?int $colorHue,
    ) {}

    public function assertValid(): void
    {
        $name = trim($this->nameAr);
        if ($name === '' || mb_strlen($name) > self::MAX_NAME || ($this->nameEn !== null && mb_strlen($this->nameEn) > self::MAX_NAME)) {
            throw LeaveRuleViolation::of('type_name_invalid');
        }
        if ($this->description !== null && mb_strlen($this->description) > 2000) {
            throw LeaveRuleViolation::of('type_description_too_long');
        }
        if ($this->colorHue !== null && ($this->colorHue < 0 || $this->colorHue > 359)) {
            throw LeaveRuleViolation::of('type_color_invalid');
        }
    }

    public static function validCode(string $code): bool
    {
        return preg_match('/^[A-Z0-9_]{2,30}$/', $code) === 1;
    }
}
