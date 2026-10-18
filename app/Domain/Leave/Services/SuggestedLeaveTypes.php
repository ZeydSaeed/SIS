<?php

namespace App\Domain\Leave\Services;

use App\Domain\Leave\Data\LeavePolicyTerms;
use App\Domain\Leave\Data\LeaveTypeFields;
use App\Domain\Leave\ValueObjects\DayBasis;
use App\Domain\Leave\ValueObjects\LeaveAudience;
use App\Domain\Leave\ValueObjects\LeaveUnit;

/**
 * A starting set of common leave types. Deliberately without entitlements or legal limits (they differ by
 * institution and country): only whether a type deducts from a balance, needs a document, and its sensitivity.
 * Types that deduct cannot be used until the school sets an entitlement and grants it.
 */
final class SuggestedLeaveTypes
{
    /** @return array<string, array{0: LeaveTypeFields, 1: LeavePolicyTerms}> */
    public static function all(): array
    {
        $type = static fn (string $ar, string $en, LeaveAudience $audience, bool $sensitive, int $hue, LeaveUnit $unit = LeaveUnit::Days): LeaveTypeFields => new LeaveTypeFields($ar, $en, null, $audience, $unit, $sensitive, $hue);
        $terms = static fn (bool $deducts, bool $document, DayBasis $basis = DayBasis::WorkingDays): LeavePolicyTerms => new LeavePolicyTerms($basis, $document, $deducts, 1, null, null);

        return [
            'ANNUAL' => [$type('إجازة اعتيادية', 'Annual leave', LeaveAudience::All, false, 205), $terms(true, false)],
            'SICK' => [$type('إجازة مرضية', 'Sick leave', LeaveAudience::All, true, 0), $terms(false, true)],
            'EMERGENCY' => [$type('إجازة طارئة', 'Emergency leave', LeaveAudience::All, false, 30), $terms(true, false)],
            'UNPAID' => [$type('إجازة بدون راتب', 'Unpaid leave', LeaveAudience::All, false, 270), $terms(false, false, DayBasis::CalendarDays)],
            'MATERNITY' => [$type('إجازة أمومة', 'Maternity leave', LeaveAudience::All, true, 320), $terms(false, true, DayBasis::CalendarDays)],
            'OFFICIAL_MISSION' => [$type('إيفاد / مهمة رسمية', 'Official mission', LeaveAudience::All, false, 160), $terms(false, false)],
            'HOURLY' => [$type('إجازة زمنية (ساعات)', 'Hourly leave', LeaveAudience::All, false, 50, LeaveUnit::Hours), $terms(true, false)],
        ];
    }
}
