<?php

namespace App\Domain\Timetable\Repositories;

/** Read side of the timetable builder (one school + year; bounded by the school's sections × periods). */
interface TimetableWorkspaceReadRepositoryInterface
{
    /**
     * Active teaching assignments that name a class (lessons to place), with the subject's
     * weekly hours from the department (or general) curriculum of the year.
     *
     * @return list<array{teacher_id: int, teacher_name: string, subject_id: int, subject_name: string, class_id: int, section_id: int|null, weekly_hours: int|null}>
     */
    public function lessons(int $schoolId, int $academicYearId): array;

    /**
     * Active schedules of the year.
     *
     * @return list<array{id: int, section_id: int, day_of_week: int, period_id: int, subject_id: int, teacher_id: int, room_id: int|null}>
     */
    public function activeSchedules(int $schoolId, int $academicYearId): array;

    /**
     * Active teachers of the school-year (id + name), for the teacher view.
     *
     * @return list<array{id: int, full_name: string, short_name: string}>
     */
    public function teachers(int $schoolId, int $academicYearId): array;

    /**
     * Which branch / department each section serves — the SSOT is the students' placement (active
     * enrollments). A mixed section serves every department that has students in it.
     *
     * @return list<array{section_id: int, branch_id: int, department_id: int|null, students: int}>
     */
    public function sectionPlacements(int $schoolId, int $academicYearId): array;

    /**
     * Who may teach what this year (teachers.teacher_subjects) — the timetable's teacher binding.
     *
     * @return list<array{teacher_id: int, subject_id: int}>
     */
    public function teacherSubjects(int $schoolId, int $academicYearId): array;

    /**
     * Personal lesson limits of the active teachers (teachers.teacher_schools) — only teachers with at least one limit.
     *
     * @return array<int, array{weekly_min: int|null, weekly_max: int|null, daily_max: int|null}>
     */
    public function teacherLimits(int $schoolId, int $academicYearId): array;

    /** @return list<int>  subjects of type «عملي» (3) */
    public function practicalSubjectIds(): array;

    /**
     * Active sections of the school-year with their class.
     *
     * @return list<array{id: int, class_id: int}>
     */
    public function sections(int $schoolId, int $academicYearId): array;
}
