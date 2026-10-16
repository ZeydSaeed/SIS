<?php

namespace App\Application\Admission\Queries;

use App\Application\Admission\Contracts\AdmissionReadRepositoryInterface;
use App\Application\Contracts\Query;
use App\Application\Contracts\QueryHandler;
use App\Domain\Admission\ValueObjects\ApplicationStatus;

/**
 * @implements QueryHandler<GetFollowUpApplicationsQuery, list<array<string, mixed>>>
 */
final class GetFollowUpApplicationsHandler implements QueryHandler
{
    public function __construct(
        private readonly AdmissionReadRepositoryInterface $admission,
    ) {}

    /**
     * The school's applications among the ids, each with the manual transitions its status allows (never
     * «converted»: that is the student conversion). Ids the school does not hold are left out.
     *
     * @return list<array<string, mixed>>
     */
    public function handle(Query $query): array
    {
        return array_map(static function (array $application): array {
            $status = ApplicationStatus::tryFrom((int) $application['status']);
            $application['allowed_transitions'] = $status === null
                ? []
                : array_values(array_map(
                    static fn (ApplicationStatus $target): int => $target->value,
                    array_filter(
                        $status->allowedTransitions(),
                        static fn (ApplicationStatus $target): bool => $target !== ApplicationStatus::Converted,
                    ),
                ));

            return $application;
        }, $this->admission->followUpApplications($query->schoolId, $query->applicationIds));
    }
}
