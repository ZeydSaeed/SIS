<?php

namespace App\Domain\Admission\Services;

use App\Domain\Admission\Exceptions\ApplicationNotFoundException;
use App\Domain\Admission\Exceptions\InvalidApplicationTransitionException;
use App\Domain\Admission\Repositories\AdmissionRepositoryInterface;
use App\Domain\Admission\ValueObjects\ApplicationStatus;
use DomainException;

/**
 * Bulk status-transition preconditions — keeps Application handler within ARCH-103.
 */
final class BulkApplicationTransitionGuard
{
    public function __construct(
        private readonly AdmissionRepositoryInterface $admission,
    ) {}

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function uniqueIds(array $ids): array
    {
        $unique = [];
        foreach ($ids as $id) {
            if ($id > 0) {
                $unique[$id] = $id;
            }
        }

        $values = array_values($unique);
        if ($values === []) {
            throw new DomainException('No admission applications selected.');
        }

        return $values;
    }

    public function requireManualTarget(int $toStatus): ApplicationStatus
    {
        $to = ApplicationStatus::tryFrom($toStatus);
        if ($to === null || $to === ApplicationStatus::Converted) {
            throw InvalidApplicationTransitionException::fromTo(0, $toStatus);
        }

        return $to;
    }

    /**
     * @param  list<int>  $applicationIds
     * @return list<array{id: int, from: ApplicationStatus}>
     */
    public function prepare(array $applicationIds, int $schoolId, ApplicationStatus $to): array
    {
        $prepared = [];
        foreach ($applicationIds as $applicationId) {
            $application = $this->admission->findApplicationForSchool($applicationId, $schoolId);
            if ($application === null) {
                throw ApplicationNotFoundException::forId($applicationId);
            }

            $from = ApplicationStatus::from($application['status']);
            if (! $from->canTransitionTo($to)) {
                throw InvalidApplicationTransitionException::fromTo($from->value, $to->value);
            }

            $prepared[] = [
                'id' => $applicationId,
                'from' => $from,
            ];
        }

        return $prepared;
    }
}
