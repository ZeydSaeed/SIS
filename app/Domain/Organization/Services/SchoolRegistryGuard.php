<?php

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Repositories\SchoolRepositoryInterface;
use App\Domain\Organization\ValueObjects\SchoolStatus;

final class SchoolRegistryGuard
{
    public function __construct(
        private readonly SchoolRepositoryInterface $schools,
    ) {}

    public function createRejectionCode(string $name, int $directorateId): ?string
    {
        if (trim($name) === '') {
            return 'organization.school_name_invalid';
        }
        if (! $this->schools->directorateIsActive($directorateId)) {
            return 'organization.directorate_invalid';
        }

        return null;
    }

    public function statusRejectionCode(int $schoolId, int $status): ?string
    {
        if (SchoolStatus::tryFrom($status) === null) {
            return 'organization.school_status_invalid';
        }
        $school = $this->schools->find($schoolId);
        if ($school === null) {
            return 'organization.school_not_found';
        }
        // A school can only be (re)activated inside an active directorate.
        if ($status === SchoolStatus::Active->value && ! $this->schools->directorateIsActive($school->directorateId)) {
            return 'organization.directorate_invalid';
        }

        return null;
    }

    /**
     * @param  array{directorate_id?: int, name?: string, address?: ?string, phone?: ?string, email?: ?string}  $fields
     */
    public function updateRejectionCode(int $schoolId, array $fields): ?string
    {
        if ($this->schools->find($schoolId) === null) {
            return 'organization.school_not_found';
        }
        if ($fields === []) {
            return 'organization.school_update_empty';
        }
        if (array_key_exists('name', $fields) && trim((string) $fields['name']) === '') {
            return 'organization.school_name_invalid';
        }
        if (array_key_exists('directorate_id', $fields)
            && ! $this->schools->directorateIsActive((int) $fields['directorate_id'])) {
            return 'organization.directorate_invalid';
        }

        return null;
    }
}
