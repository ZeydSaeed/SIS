<?php

namespace App\Application\Enrollment\Queries;

use App\Application\Contracts\Query;

/** «الاختصار واللون» of the year's classes and sections (the «الصفوف والشعب» page and the timetable). */
final readonly class GetStructureAppearanceQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
    ) {}
}
