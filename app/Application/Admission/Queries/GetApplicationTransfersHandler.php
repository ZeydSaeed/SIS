<?php

namespace App\Application\Admission\Queries;

use App\Application\Admission\Contracts\AdmissionReadRepositoryInterface;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;

final class GetApplicationTransfersHandler implements QueryHandler
{
    private const HISTORY_LIMIT = 50;

    public function __construct(
        private readonly AdmissionReadRepositoryInterface $admission,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(Query $query): array
    {
        assert($query instanceof GetApplicationTransfersQuery);

        $page = max(1, $query->page);
        $list = $this->admission->transferableApplications($query->schoolId, $query->search, $page, $query->perPage);

        return [
            'applications' => $list['rows'],
            'pagination' => [
                'page' => $page,
                'per_page' => $query->perPage,
                'total' => $list['total'],
                'total_pages' => max(1, (int) ceil($list['total'] / $query->perPage)),
            ],
            'history' => $this->admission->transferHistory($query->schoolId, self::HISTORY_LIMIT),
            'schools' => array_map(
                static fn (array $school): array => ['id' => $school['id'], 'name' => $school['name']],
                $this->admission->schoolOptions($query->allowedSchoolIds),
            ),
            'periods' => $this->admission->activePeriodsWithYears(),
        ];
    }
}
