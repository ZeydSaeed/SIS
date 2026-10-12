<?php

namespace App\Application\Attendance\Queries;

use App\Application\Attendance\Support\SheetActor;
use App\Application\Contracts\Query;

/** «جدولي اليوم»: the lessons a teacher teaches on a date with their sheet state. */
final readonly class GetTeacherDayQuery implements Query
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public ?string $date,
        public SheetActor $actor,
        public ?int $teacherId = null,
    ) {}
}
