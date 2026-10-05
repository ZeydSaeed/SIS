<?php

namespace App\Domain\Admission\Services;

/**
 * A period belongs to a directorate (المديرية): the admission page lists the periods of
 * the user's directorates, and a school applies only in its own directorate's periods.
 * Periods without a directorate are legacy periods shared by every school.
 */
final class AdmissionPeriodDirectorateScope
{
    /**
     * @param  list<array<string, mixed>>  $schoolOptions  The user's schools (id, directorate_id, ...).
     */
    public function directorateOfSchool(array $schoolOptions, int $schoolId): ?int
    {
        foreach ($schoolOptions as $school) {
            if ((int) $school['id'] === $schoolId) {
                return isset($school['directorate_id']) ? (int) $school['directorate_id'] : null;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $schoolOptions
     * @return list<int>
     */
    public function directoratesOf(array $schoolOptions): array
    {
        return array_values(array_unique(array_map('intval', array_filter(array_column($schoolOptions, 'directorate_id')))));
    }

    /**
     * Schools whose follow-up roster the user sees: the current school and every linked one
     * (none when the roster was not requested).
     *
     * @param  list<array<string, mixed>>  $schoolOptions
     * @return list<int>
     */
    public function rosterSchoolIds(array $schoolOptions, int $currentSchoolId, bool $requested = true): array
    {
        if (! $requested) {
            return [];
        }

        return array_values(array_unique([$currentSchoolId, ...array_map('intval', array_column($schoolOptions, 'id'))]));
    }

    /**
     * Periods a school applies in: its directorate's periods plus legacy shared ones.
     *
     * @param  list<array<string, mixed>>  $periods
     * @param  list<array<string, mixed>>  $schoolOptions
     * @return list<array<string, mixed>>
     */
    public function periodsOfSchool(array $periods, array $schoolOptions, int $schoolId): array
    {
        $directorateId = $this->directorateOfSchool($schoolOptions, $schoolId);

        return $this->periodsOf($periods, $directorateId === null ? [] : [$directorateId]);
    }

    /**
     * Periods of the given directorates plus legacy shared periods (no directorate).
     *
     * @param  list<array<string, mixed>>  $periods
     * @param  list<int>  $directorateIds
     * @return list<array<string, mixed>>
     */
    public function periodsOf(array $periods, array $directorateIds): array
    {
        return array_values(array_filter(
            $periods,
            static fn (array $period): bool => ($period['directorate_id'] ?? null) === null
                || in_array((int) $period['directorate_id'], $directorateIds, true),
        ));
    }
}
