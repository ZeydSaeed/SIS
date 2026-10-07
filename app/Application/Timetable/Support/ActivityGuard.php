<?php

namespace App\Application\Timetable\Support;

use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableEngineReadRepositoryInterface;
use App\Domain\Timetable\Repositories\TimetableWorkspaceReadRepositoryInterface;
use App\Domain\Timetable\Specifications\ActivityDefinitionRules;

/** Checks an activity definition against the Domain rules and the school's own data (ownership, groups, G5). */
final class ActivityGuard
{
    public function __construct(
        private readonly TimetableEngineReadRepositoryInterface $engine,
        private readonly TimetableWorkspaceReadRepositoryInterface $workspace,
        private readonly TimetableConfigurationRepositoryInterface $config,
    ) {}

    /**
     * @param  array{subject_id?: int, activity_type: int, weekly_count: int, block_length: int, distribution: string|null, room_id: int|null, room_type: int|null, workshop_id: int|null, week_pattern: int, term_id?: int|null}  $data
     * @param  list<array{section_id: int, group_id: int|null}>|null  $targets
     * @param  list<array{teacher_id: int, role: int, sessions: int|null}>|null  $teachers
     * @return list<string>
     */
    public function errors(int $schoolId, int $academicYearId, array $data, ?array $targets, ?array $teachers): array
    {
        $settings = $this->engine->settings($schoolId, $academicYearId);
        $groupSection = array_map(static fn (array $g): int => $g['section_id'], $this->engine->groups($schoolId, $academicYearId));
        $leadQualified = null;
        if ($teachers !== null && isset($data['subject_id'])) {
            $lead = array_values(array_filter($teachers, static fn (array $t): bool => $t['role'] === 1))[0]['teacher_id'] ?? null;
            $assigned = array_filter($this->workspace->teacherSubjects($schoolId, $academicYearId),
                static fn (array $r): bool => $r['teacher_id'] === $lead && $r['subject_id'] === $data['subject_id']);
            $leadQualified = $lead !== null && $assigned !== [];
        }
        $errors = ActivityDefinitionRules::errors($data, $targets, $teachers, $settings?->cycleWeeks ?? 1, $groupSection, $leadQualified);
        if ($errors !== []) {
            return $errors;
        }
        $owned = $this->config->referencesBelongToSchool($schoolId, $academicYearId, [
            'section_ids' => array_column($targets ?? [], 'section_id'),
            'group_ids' => array_filter(array_column($targets ?? [], 'group_id')),
            'teacher_ids' => array_column($teachers ?? [], 'teacher_id'),
            'room_ids' => [$data['room_id']],
            'workshop_ids' => [$data['workshop_id']],
            'subject_ids' => [$data['subject_id'] ?? null],
            'term_ids' => [$data['term_id'] ?? null],
        ]);

        return $owned ? [] : ['timetable.reference_not_in_school'];
    }
}
