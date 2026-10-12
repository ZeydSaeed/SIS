<?php

namespace App\Domain\Timetable\Repositories;

/**
 * A teacher who also teaches in another school of the system is busy there at fixed times. The timetable of one
 * school never sees another school's lessons (tenant isolation); this port answers only *when* the given teachers
 * are taken elsewhere — no section, subject, room or school is returned.
 */
interface TeacherElsewhereRepositoryInterface
{
    /**
     * Active lessons (lead or co-teacher, joined-class rows once) of these teachers in the other schools, same
     * academic year, as times of day.
     *
     * @param  list<int>  $teacherIds
     * @return list<array{teacher_id: int, day: int, start: string, end: string, week_no: int|null}>
     */
    public function busyElsewhere(int $schoolId, int $academicYearId, array $teacherIds): array;
}
