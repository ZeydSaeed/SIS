<?php

namespace App\Application\Timetable\Queries;

/** «جدول الطالب»: a student's week (date = which published version; null = today). */
final readonly class GetStudentTimetableQuery
{
    public function __construct(
        public int $schoolId,
        public int $academicYearId,
        public int $studentId,
        public ?string $date = null,
    ) {}
}
