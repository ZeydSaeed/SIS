<?php

namespace App\Domain\Timetable\Repositories;

use App\Domain\Timetable\Support\TimetableSettings;

/**
 * Writes the engine configuration. Rows are never deleted: activities, divisions, rules and availability end
 * by status (activities / rules also by effective_to). Call inside the handler's transaction.
 */
interface TimetableConfigurationRepositoryInterface
{
    /** @param  array<string, mixed>  $display  normalised «تنسيق الجدول» (TimetableDisplaySettings) */
    public function saveDisplay(int $schoolId, int $academicYearId, array $display, ?int $userId): void;

    public function saveSettings(int $schoolId, int $academicYearId, TimetableSettings $settings, ?int $userId): void;

    /**
     * @param  array{subject_id: int, activity_type: int, weekly_count: int, block_length: int, distribution: string|null, room_id: int|null, room_type: int|null, workshop_id: int|null, week_pattern: int, term_id: int|null, note: string|null}  $data
     * @param  list<array{section_id: int, group_id: int|null}>  $targets
     * @param  list<array{teacher_id: int, role: int, sessions: int|null}>  $teachers
     */
    public function insertActivity(int $schoolId, int $academicYearId, array $data, array $targets, array $teachers, ?int $userId): int;

    /** @param  array{activity_type: int, weekly_count: int, block_length: int, distribution: string|null, room_id: int|null, room_type: int|null, workshop_id: int|null, week_pattern: int, note: string|null}  $data */
    public function updateActivity(int $schoolId, int $activityId, array $data): bool;

    public function endActivity(int $schoolId, int $activityId, string $onDate): bool;

    /** @return array{id: int, academic_year_id: int, status: int, subject_id: int}|null */
    public function findActivity(int $schoolId, int $activityId): ?array;

    /**
     * @param  list<array{name: string, student_count: int|null, members: list<int>}>  $groups
     * @return list<int> group ids
     */
    public function insertDivision(int $schoolId, int $academicYearId, int $sectionId, string $name, array $groups): array;

    public function endDivision(int $schoolId, int $divisionId, string $onDate): bool;

    /**
     * Sets (kind) or clears (null) a target's slots; returns how many slots changed.
     *
     * @param  array{teacher_id?: int|null, room_id?: int|null, section_id?: int|null, workshop_id?: int|null}  $target
     * @param  list<array{day: int, period_id: int}>  $slots
     */
    public function setAvailability(int $schoolId, int $academicYearId, array $target, array $slots, ?int $kind, ?int $weekNo, ?string $reason, ?int $userId): int;

    /** @param  array<string, int|null>  $scope */
    public function insertRule(int $schoolId, int $academicYearId, string $type, int $priority, array $scope, array $params, ?string $reason, ?int $userId): int;

    public function endRule(int $schoolId, int $ruleId, string $onDate): bool;

    /** True when every referenced id belongs to the school (and year where academic). */
    public function referencesBelongToSchool(int $schoolId, int $academicYearId, array $refs): bool;
}
