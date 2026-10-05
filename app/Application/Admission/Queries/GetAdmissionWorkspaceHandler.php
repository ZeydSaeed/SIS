<?php

namespace App\Application\Admission\Queries;

use App\Application\Admission\Contracts\AdmissionReadRepositoryInterface;
use App\Application\Admission\DTOs\AdmissionWorkspaceDTO;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Admission\Services\ActiveAdmissionPeriodSummarizer;
use App\Domain\Admission\Services\AdmissionPeriodDirectorateScope;
use App\Domain\Admission\Services\AdmissionWorkflowProgressCalculator;
use App\Domain\Admission\ValueObjects\ApplicationStatus;

final class GetAdmissionWorkspaceHandler implements QueryHandler
{
    public function __construct(
        private readonly AdmissionReadRepositoryInterface $admission,
        private readonly AdmissionWorkflowProgressCalculator $progress,
        private readonly ActiveAdmissionPeriodSummarizer $activePeriods,
        private readonly AdmissionPeriodDirectorateScope $directorates,
    ) {}

    public function handle(Query $query): AdmissionWorkspaceDTO
    {
        assert($query instanceof GetAdmissionWorkspaceQuery);

        $schoolOptions = $this->admission->schoolOptions($query->allowedSchoolIds);
        $shell = $this->admission->periodShell($query->schoolId, $query->academicYearId);
        // The current school applies only in its directorate's periods (NULL = legacy shared period).
        $summaries = $this->activePeriods->summarize(
            $this->directorates->periodsOfSchool($shell['periods'], $schoolOptions, $query->schoolId),
            $shell['period_counts'] ?? [],
        );

        $wantsAllPeriods = $query->applicationPeriodId === 0;
        $selectedId = $wantsAllPeriods
            ? 0
            : $this->activePeriods->resolveSelectedId($summaries, $query->applicationPeriodId);
        $statusScopePeriodId = $wantsAllPeriods ? null : $selectedId;
        $selected = $wantsAllPeriods
            ? null
            : $this->activePeriods->selectedSummary($summaries, $selectedId);

        $workspace = $this->admission->workspace(
            $query->schoolId,
            $query->academicYearId,
            $query->statusFilter,
            $query->includeApplications,
            $query->page,
            $query->perPage,
            $statusScopePeriodId,
            $query->search,
            $query->enrollmentStatus,
            false,
        );

        return new AdmissionWorkspaceDTO(
            // The periods table: every directorate of the user's schools.
            periods: $this->directorates->periodsOf($workspace['periods'], $this->directorates->directoratesOf($schoolOptions)),
            applications: $workspace['applications'],
            documents: $workspace['documents'],
            gradeLevels: $workspace['grade_levels'],
            schools: $workspace['schools'],
            branches: $workspace['branches'] ?? [],
            departments: $workspace['departments'],
            specializations: $workspace['specializations'],
            workflowSteps: $workspace['workflow_steps'],
            workflowProgress: $this->workflowProgress(
                $workspace['status_counts'] ?? [],
                $summaries,
                $selected,
            ),
            activePeriods: $summaries,
            selectedPeriodId: $selectedId,
            pagination: $workspace['pagination'] ?? [
                'page' => 1,
                'per_page' => $query->perPage,
                'total' => 0,
                'total_pages' => 1,
            ],
            statusTransitions: $this->statusTransitions(),
            // Students of every school of the user, each row tagged with its school.
            acceptedStudents: $this->admission->acceptedRoster(
                $this->directorates->rosterSchoolIds($schoolOptions, $query->schoolId, $query->includeAcceptedStudents),
                $query->academicYearId,
            ),
            periodCounts: $workspace['period_counts'] ?? [],
            acceptedStudentsIncluded: $query->includeAcceptedStudents,
            schoolOptions: $schoolOptions,
        );
    }

    /**
     * @return array<int, list<int>>
     */
    private function statusTransitions(): array
    {
        $map = [];
        foreach (ApplicationStatus::cases() as $status) {
            $map[$status->value] = array_map(
                static fn (ApplicationStatus $s): int => $s->value,
                $status->allowedTransitions(),
            );
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $statusCounts
     * @param  list<array{id:int, max_applications:?int, total_count:int}>  $activeSummaries
     * @param  array<string, mixed>|null  $selected
     * @return array{overall_percent: int, stages: list<array{status:int, percent:int, count:int}>}
     */
    private function workflowProgress(array $statusCounts, array $activeSummaries, ?array $selected): array
    {
        $pipeline = [];
        foreach (ApplicationStatus::pipelineSteps() as $status) {
            $pipeline[] = $status->value;
        }

        $calculated = $this->progress->calculateFromCounts($pipeline, $statusCounts);
        $totalApps = 0;
        foreach ($statusCounts as $count) {
            $totalApps += (int) $count;
        }

        $stages = [
            ['status' => 0, 'percent' => $totalApps === 0 ? 0 : 100, 'count' => $totalApps],
        ];
        foreach ($pipeline as $status) {
            $count = (int) ($statusCounts[$status] ?? 0);
            // Ribbon «الطلبة المقبولين» shows accepted + converted (auto-convert on accept).
            if ($status === ApplicationStatus::Accepted->value) {
                $count += (int) ($statusCounts[ApplicationStatus::Converted->value] ?? 0);
            }
            $stages[] = [
                'status' => $status,
                'percent' => $calculated['stage_percents'][$status] ?? 0,
                'count' => $count,
            ];
        }

        $overall = $calculated['overall_percent'];
        if (is_array($selected) && is_int($selected['max_applications'] ?? null)) {
            $overall = $this->activePeriods->capacityPercent(
                (int) $selected['max_applications'],
                (int) ($selected['total_count'] ?? 0),
            );
        } else {
            $combinedMax = 0;
            $combinedTotal = 0;
            $hasBoundedCapacity = false;
            foreach ($activeSummaries as $summary) {
                if ($summary['max_applications'] === null) {
                    continue;
                }
                $hasBoundedCapacity = true;
                $combinedMax += (int) $summary['max_applications'];
                $combinedTotal += (int) $summary['total_count'];
            }
            if ($hasBoundedCapacity) {
                $overall = $this->activePeriods->capacityPercent($combinedMax, $combinedTotal);
            }
        }

        return [
            'overall_percent' => $overall,
            'stages' => $stages,
        ];
    }
}
