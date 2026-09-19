<?php

namespace App\Application\Admission\Queries;

use App\Application\Admission\Contracts\AdmissionReadRepositoryInterface;
use App\Application\Admission\DTOs\AdmissionWorkspaceDTO;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Admission\Services\ActiveAdmissionPeriodSummarizer;
use App\Domain\Admission\Services\AdmissionWorkflowProgressCalculator;
use App\Domain\Admission\ValueObjects\ApplicationStatus;

final class GetAdmissionWorkspaceHandler implements QueryHandler
{
    public function __construct(
        private readonly AdmissionReadRepositoryInterface $admission,
        private readonly AdmissionWorkflowProgressCalculator $progress,
        private readonly ActiveAdmissionPeriodSummarizer $activePeriods,
    ) {}

    public function handle(Query $query): AdmissionWorkspaceDTO
    {
        assert($query instanceof GetAdmissionWorkspaceQuery);

        $workspace = $this->admission->workspace($query->schoolId, $query->academicYearId);
        $summaries = $this->activePeriods->summarize(
            $workspace['periods'],
            $workspace['period_counts'] ?? [],
        );
        $selectedId = $this->activePeriods->resolveSelectedId(
            $summaries,
            $query->applicationPeriodId,
        );
        $applications = $this->activePeriods->applicationsInActivePeriods(
            $workspace['applications'],
            $summaries,
        );
        $selected = $this->activePeriods->selectedSummary($summaries, $selectedId);

        return new AdmissionWorkspaceDTO(
            periods: $workspace['periods'],
            applications: $applications,
            documents: $this->activePeriods->documentsForApplications(
                $workspace['documents'],
                $applications,
            ),
            gradeLevels: $workspace['grade_levels'],
            schools: $workspace['schools'],
            departments: $workspace['departments'],
            specializations: $workspace['specializations'],
            workflowSteps: $workspace['workflow_steps'],
            workflowProgress: $this->workflowProgress($applications, $summaries, $selected),
            activePeriods: $summaries,
            selectedPeriodId: $selectedId,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $applications
     * @param  list<array{id:int, max_applications:?int, total_count:int}>  $activeSummaries
     * @param  array<string, mixed>|null  $selected
     * @return array{overall_percent: int, stages: list<array{status:int, percent:int}>}
     */
    private function workflowProgress(array $applications, array $activeSummaries, ?array $selected): array
    {
        $pipeline = [];
        foreach (ApplicationStatus::pipelineSteps() as $status) {
            $pipeline[] = $status->value;
        }

        $statuses = [];
        foreach ($applications as $application) {
            $statuses[] = (int) $application['status'];
        }

        $calculated = $this->progress->calculate($pipeline, $statuses);
        $stages = [
            ['status' => 0, 'percent' => $statuses === [] ? 0 : 100],
        ];
        foreach ($pipeline as $status) {
            $stages[] = [
                'status' => $status,
                'percent' => $calculated['stage_percents'][$status] ?? 0,
            ];
        }

        $overall = $calculated['overall_percent'];
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
        } elseif (is_array($selected) && is_int($selected['max_applications'] ?? null)) {
            $overall = $this->activePeriods->capacityPercent(
                (int) $selected['max_applications'],
                (int) ($selected['total_count'] ?? 0),
            );
        }

        return [
            'overall_percent' => $overall,
            'stages' => $stages,
        ];
    }
}
