<?php

namespace App\Application\Timetable\Support;

use App\Domain\Timetable\Repositories\TimetableConfigurationRepositoryInterface;
use App\Domain\Timetable\Services\TimetableAdvisor;
use App\Domain\Timetable\ValueObjects\GenerationMode;

/**
 * READY TO GENERATE or BLOCKED (spec §50): the advisor's blockers that touch the run's scope stop it — relaxed
 * mode lets it run and report. Scope ids must belong to the school.
 */
final class GenerationGate
{
    public function __construct(
        private readonly TimetableBoardLoader $boards,
        private readonly TimetableAdvisor $advisor,
        private readonly TimetableConfigurationRepositoryInterface $config,
    ) {}

    /**
     * @param  array<string, mixed>  $scope
     * @return array{errors: list<string>, blockers: list<array<string, mixed>>}
     */
    public function check(int $schoolId, int $academicYearId, GenerationMode $mode, array $scope): array
    {
        $owned = $this->config->referencesBelongToSchool($schoolId, $academicYearId, [
            'section_ids' => $scope['section_ids'] ?? [],
            'teacher_ids' => $scope['teacher_ids'] ?? [],
            'subject_ids' => $scope['subject_ids'] ?? [],
            'branch_ids' => [$scope['branch_id'] ?? null],
            'department_ids' => [$scope['department_id'] ?? null],
            'class_ids' => [$scope['class_id'] ?? null],
        ]);
        if (! $owned) {
            return ['errors' => ['timetable.reference_not_in_school'], 'blockers' => []];
        }
        $board = $this->boards->load($schoolId, $academicYearId);
        $resolved = GenerationScope::resolve($board, $scope);
        if ($resolved['section_ids'] === []) {
            return ['errors' => ['timetable.generation_scope_empty'], 'blockers' => []];
        }
        $advice = $this->advisor->advise($board);
        $blockers = array_values(array_filter($advice['findings'], static fn (array $f): bool => $f['severity'] === 'blocker'
            && ($f['section_id'] === null || $resolved['section_ids'] === null || in_array($f['section_id'], $resolved['section_ids'], true))
            && ($f['teacher_id'] === null || $resolved['teacher_ids'] === null || in_array($f['teacher_id'], $resolved['teacher_ids'], true))));
        if ($blockers !== [] && $mode !== GenerationMode::Relaxed) {
            return ['errors' => ['timetable.generation_blocked'], 'blockers' => $blockers];
        }

        return ['errors' => [], 'blockers' => $blockers];
    }
}
