<?php

namespace App\Domain\Admission\Services;

use App\Domain\Admission\ValueObjects\ApplicationPeriodStatus;

/**
 * Active period headline, selection, and table scoping.
 */
final class ActiveAdmissionPeriodSummarizer
{
    /**
     * @param  list<array<string, mixed>>  $periods
     * @param  array<int, array{total:int, submitted:int}>  $countsByPeriodId
     * @return list<array{id:int, name:string, start_date:string, max_applications:?int, total_count:int, submitted_count:int, remaining:?int}>
     */
    public function summarize(array $periods, array $countsByPeriodId): array
    {
        $summaries = [];
        foreach ($periods as $period) {
            if ((int) $period['status'] !== ApplicationPeriodStatus::Active->value) {
                continue;
            }

            $id = (int) $period['id'];
            $max = $period['max_applications'] !== null ? (int) $period['max_applications'] : null;
            $total = (int) ($countsByPeriodId[$id]['total'] ?? 0);
            $submitted = (int) ($countsByPeriodId[$id]['submitted'] ?? 0);

            $summaries[] = [
                'id' => $id,
                'name' => (string) $period['name'],
                'start_date' => (string) ($period['start_date'] ?? ''),
                'max_applications' => $max,
                'total_count' => $total,
                'submitted_count' => $submitted,
                'remaining' => $max === null ? null : max(0, $max - $total),
            ];
        }

        usort(
            $summaries,
            static function (array $left, array $right): int {
                $byDate = strcmp($left['start_date'], $right['start_date']);
                if ($byDate !== 0) {
                    return $byDate;
                }

                return $left['id'] <=> $right['id'];
            },
        );

        return $summaries;
    }

    /**
     * @param  list<array{id:int}>  $summaries
     */
    public function resolveSelectedId(array $summaries, ?int $requestedId): ?int
    {
        if ($summaries === []) {
            return null;
        }

        if ($requestedId !== null) {
            foreach ($summaries as $summary) {
                if ($summary['id'] === $requestedId) {
                    return $requestedId;
                }
            }
        }

        return $summaries[0]['id'];
    }

    /**
     * @param  list<array<string, mixed>>  $applications
     * @return list<array<string, mixed>>
     */
    public function applicationsInPeriod(array $applications, ?int $periodId): array
    {
        if ($periodId === null) {
            return [];
        }

        $scoped = [];
        foreach ($applications as $application) {
            if ((int) $application['application_period_id'] === $periodId) {
                $scoped[] = $application;
            }
        }

        return $scoped;
    }

    /**
     * @param  list<array<string, mixed>>  $documents
     * @param  list<array<string, mixed>>  $applications
     * @return list<array<string, mixed>>
     */
    public function documentsForApplications(array $documents, array $applications): array
    {
        $ids = [];
        foreach ($applications as $application) {
            $ids[(int) $application['id']] = true;
        }

        $scoped = [];
        foreach ($documents as $document) {
            if (isset($ids[(int) $document['application_id']])) {
                $scoped[] = $document;
            }
        }

        return $scoped;
    }

    public function capacityPercent(?int $maxApplications, int $totalCount): int
    {
        if ($maxApplications === null || $maxApplications <= 0 || $totalCount <= 0) {
            return 0;
        }

        $percent = (int) round(($totalCount / $maxApplications) * 100);

        return $percent > 100 ? 100 : $percent;
    }
}
