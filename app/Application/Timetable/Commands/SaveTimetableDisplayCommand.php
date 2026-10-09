<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** «تنسيق الجدول»: the school-year's cell layout, fields, fonts and sizes (normalised by TimetableDisplaySettings). */
final readonly class SaveTimetableDisplayCommand implements Command
{
    /** @param  array<string, mixed>  $display */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public array $display,
        public ?int $userId,
        public ?string $idempotencyKey,
    ) {}
}
