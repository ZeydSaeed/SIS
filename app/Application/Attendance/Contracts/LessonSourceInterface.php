<?php

namespace App\Application\Attendance\Contracts;

use App\Domain\Attendance\Data\LessonSlot;

/**
 * The effective timetable as Attendance sees it (Timetable stays the source of truth for lessons, times, rooms
 * and substitutes). Implemented over the Timetable application query — never by reading timetable tables here.
 */
interface LessonSourceInterface
{
    /**
     * @return list<LessonSlot> ordered by period start
     */
    public function lessonsOn(int $schoolId, int $academicYearId, string $date, ?int $teacherId = null, ?int $sectionId = null): array;

    public function lesson(int $schoolId, int $academicYearId, string $date, int $scheduleId): ?LessonSlot;
}
