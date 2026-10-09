<?php

namespace App\Application\Timetable\Queries;

use App\Application\Contracts\Query;

/** Names, abbreviations and colours of everything the timetable shows (read from the owning entities). */
final readonly class GetTimetableDisplayCatalogQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
    ) {}
}
