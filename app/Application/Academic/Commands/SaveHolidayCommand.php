<?php

namespace App\Application\Academic\Commands;

use App\Application\Contracts\Command;

/** «الاجازات»: creates a school holiday (holidayId null — the academic year is required) or edits one. */
final readonly class SaveHolidayCommand implements Command
{
    public function __construct(
        public int $schoolId,
        public ?int $holidayId,
        public ?int $academicYearId,
        public string $name,
        public string $startDate,
        public string $endDate,
        public int $holidayType,
        public ?string $idempotencyKey,
    ) {}
}
