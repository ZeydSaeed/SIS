<?php

namespace App\Application\Timetable\DTOs;

/**
 * The engine side of the «الجدول الدراسي» page: settings, activities, groups, availability, rules (with the
 * rule catalogue), rooms, workshops, recent generation runs, versions, and whether the published timetable is
 * stale against the live SIS data.
 */
final readonly class TimetableEngineDTO
{
    /**
     * @param  array<string, mixed>  $settings
     * @param  list<array<string, mixed>>  $activities
     * @param  list<array<string, mixed>>  $groups
     * @param  list<array<string, mixed>>  $availability
     * @param  list<array<string, mixed>>  $rules
     * @param  list<array{type: string, kind: string, scopes: list<string>, params: array<string, string>}>  $catalogue
     * @param  list<array<string, mixed>>  $rooms
     * @param  list<array<string, mixed>>  $workshops
     * @param  list<array<string, mixed>>  $runs
     * @param  list<array<string, mixed>>  $versions
     * @param  array{published_version_id: int|null, effective_version_id: int|null, stale: bool, fingerprint: string}  $status
     */
    public function __construct(
        public array $settings,
        public array $activities,
        public array $groups,
        public array $availability,
        public array $rules,
        public array $catalogue,
        public array $rooms,
        public array $workshops,
        public array $runs,
        public array $versions,
        public array $status,
        /** Every room and workshop of the school, any status, with how many lessons / activities use it. */
        public array $places = ['rooms' => [], 'workshops' => []],
    ) {}
}
