<?php

namespace App\Domain\Timetable\Repositories;

use App\Domain\Timetable\Support\TimetableSettings;

/** Reads the engine side of one school-year: settings, activities, groups, availability, rules, rooms, workshops. */
interface TimetableEngineReadRepositoryInterface
{
    public function settings(int $schoolId, int $academicYearId): ?TimetableSettings;

    /** @return array<string, mixed>|null  the stored «تنسيق الجدول» (null = never saved) */
    public function display(int $schoolId, int $academicYearId): ?array;

    /**
     * Active activities with targets and teachers (+ display fields: activity_type, distribution text, note).
     *
     * @return list<array<string, mixed>>
     */
    public function activities(int $schoolId, int $academicYearId): array;

    /** @return array<int, array{id: int, division_id: int, section_id: int, name: string, student_count: int|null, division_name: string, members: int}> */
    public function groups(int $schoolId, int $academicYearId): array;

    /** @return list<array{id: int, teacher_id: int|null, room_id: int|null, section_id: int|null, workshop_id: int|null, day: int, period_id: int, week_no: int|null, kind: int}> */
    public function availability(int $schoolId, int $academicYearId): array;

    /** @return list<array{id: int, rule_type: string, priority: int, scope: array<string, int|null>, params: array<string, mixed>, reason: string|null, created_at: string}> */
    public function rules(int $schoolId, int $academicYearId, string $onDate): array;

    /** @return array<int, array{id: int, code: string, name: string, capacity: int|null, room_type: int|null}> */
    public function rooms(int $schoolId): array;

    /** @return array<int, array{id: int, code: string, name: string, capacity: int, safety_capacity: int, room_id: int|null}> */
    public function workshops(int $schoolId): array;

    /** @return array<int, array{class_id: int, grade_level_id: int|null, branch_ids: list<int>, department_ids: list<int>, students: int}> */
    public function sectionInfo(int $schoolId, int $academicYearId): array;

    /** @return list<int> the enrollment's active groups */
    public function groupsOfEnrollment(int $schoolId, int $enrollmentId): array;

    /** @return list<int> active enrollments of a section, in a stable order (name) */
    public function sectionEnrollmentIds(int $schoolId, int $academicYearId, int $sectionId): array;

    /** @return array{id: int, section_id: int, academic_year_id: int, student_id: int}|null the student's active enrollment in the school-year */
    public function activeEnrollmentOfStudent(int $schoolId, int $academicYearId, int $studentId): ?array;

    public function academicYearStart(int $academicYearId): ?string;
}
