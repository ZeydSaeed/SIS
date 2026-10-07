<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/**
 * «الإتاحة»: marks slots of one teacher / room / section / workshop unavailable (1), to avoid (2) or preferred
 * (3) — or clears them (kind null).
 */
final readonly class SetTimetableAvailabilityCommand implements Command
{
    /** @param  list<array{day: int, period_id: int}>  $slots */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public string $targetType,
        public int $targetId,
        public array $slots,
        public ?int $kind,
        public ?int $weekNo,
        public ?string $reason,
        public ?int $userId,
        public ?string $idempotencyKey = null,
    ) {}
}
