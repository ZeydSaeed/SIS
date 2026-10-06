<?php

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Repositories\DirectorateRepositoryInterface;
use App\Domain\Organization\Repositories\SchoolRepositoryInterface;
use App\Domain\Organization\ValueObjects\DirectorateStatus;

final class DirectorateRegistryGuard
{
    public function __construct(
        private readonly DirectorateRepositoryInterface $directorates,
        private readonly SchoolRepositoryInterface $schools,
    ) {}

    /**
     * @param  list<int>  $schoolIds  Schools to place in the new directorate.
     * @param  list<int>  $allowedSchoolIds  Schools the user is linked to.
     */
    public function createRejectionCode(string $name, ?int $ministryId, array $schoolIds, array $allowedSchoolIds): ?string
    {
        if (trim($name) === '') {
            return 'organization.directorate_name_invalid';
        }
        if ($ministryId === null) {
            return 'organization.ministry_missing';
        }

        return $this->schoolsRejection($schoolIds, $allowedSchoolIds);
    }

    /**
     * @param  array{name?: string, region?: ?string}  $fields
     * @param  list<int>|null  $schoolIds  Desired set of the user's schools in the directorate (null = unchanged).
     * @param  list<int>  $allowedSchoolIds
     */
    public function updateRejectionCode(int $directorateId, array $fields, ?array $schoolIds, array $allowedSchoolIds): ?string
    {
        $current = $this->directorates->find($directorateId);
        if ($current === null) {
            return 'organization.directorate_not_found';
        }
        if ($fields === [] && $schoolIds === null) {
            return 'organization.directorate_update_empty';
        }
        if (array_key_exists('name', $fields) && trim((string) $fields['name']) === '') {
            return 'organization.directorate_name_invalid';
        }
        if ($schoolIds === null) {
            return null;
        }

        $error = $this->schoolsRejection($schoolIds, $allowedSchoolIds);
        if ($error !== null) {
            return $error;
        }
        // Every school needs a directorate: a school leaves only by being placed in another one.
        $alreadyIn = $this->directorates->schoolIdsIn($directorateId, $allowedSchoolIds);
        if (array_diff($alreadyIn, $schoolIds) !== []) {
            return 'organization.directorate_school_removal';
        }
        if ($current->status !== DirectorateStatus::Active->value
            && array_diff($schoolIds, $alreadyIn) !== []) {
            return 'organization.directorate_inactive';
        }

        return null;
    }

    public function statusRejectionCode(int $directorateId, int $status): ?string
    {
        if (DirectorateStatus::tryFrom($status) === null) {
            return 'organization.directorate_status_invalid';
        }
        if ($this->directorates->find($directorateId) === null) {
            return 'organization.directorate_not_found';
        }
        if ($status !== DirectorateStatus::Active->value
            && $this->directorates->activeSchoolCount($directorateId) > 0) {
            return 'organization.directorate_has_schools';
        }

        return null;
    }

    /**
     * @param  list<int>  $schoolIds
     * @param  list<int>  $allowedSchoolIds
     */
    private function schoolsRejection(array $schoolIds, array $allowedSchoolIds): ?string
    {
        foreach ($schoolIds as $schoolId) {
            if (! in_array($schoolId, $allowedSchoolIds, true)) {
                return 'organization.school_not_linked';
            }
            if ($this->schools->find($schoolId) === null) {
                return 'organization.school_not_found';
            }
        }

        return null;
    }
}
