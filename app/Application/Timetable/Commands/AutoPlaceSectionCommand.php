<?php

namespace App\Application\Timetable\Commands;

use App\Application\Contracts\Command;

/** «توزيع تلقائي»: places the remaining lessons of one or more sections on free cells (in the given order). */
final readonly class AutoPlaceSectionCommand implements Command
{
    /** @param  list<int>  $sectionIds */
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public array $sectionIds,
        public string $idempotencyKey,
        public ?int $createdBy = null,
        public ?string $correlationId = null,
    ) {}
}
