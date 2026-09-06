<?php

namespace App\Intelligence\Enums;

enum RiskTier: int
{
    case Observe = 0;
    case SafeAuto = 1;
    case HumanReview = 2;
    case AdrDba = 3;
    case Forbidden = 4;

    public function canAutoExecute(int $maxAutoTier): bool
    {
        return $this->value <= $maxAutoTier && $this !== self::Forbidden;
    }

    public function requiresApproval(): bool
    {
        return $this->value >= self::HumanReview->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Observe => 'observe',
            self::SafeAuto => 'safe_auto',
            self::HumanReview => 'human_review',
            self::AdrDba => 'adr_dba',
            self::Forbidden => 'forbidden',
        };
    }
}
