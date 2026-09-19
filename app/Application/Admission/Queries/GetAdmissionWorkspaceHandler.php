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
        $applications = $this->activePeriods->applicationsInPeriod(
            $workspace['applications'],
            $selectedId,
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
            workflowProgress: $this->workflowProgress($applications, $selected),
            activePeriods: $summaries,
            selectedPeriodId: $selectedId,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $applications
     * @param  array<string, mixed>|null  $selected
     * @return array{overall_percent: int, stages: list<array{status:int, percent:int}>}
     */
    private function workflowProgress(array $applications, ?array $selected): array
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
        if ($selected !== null && array_key_exists('max_applications', $selected)) {
            $overall = $this->activePeriods->capacityPercent(
                $selected['max_applications'] !== null ? (int) $selected['max_applications'] : null,
                (int) ($selected['total_count'] ?? 0),
            );
        }

        return [
            'overall_percent' => $overall,
            'stages' => $stages,
        ];
    }
}
