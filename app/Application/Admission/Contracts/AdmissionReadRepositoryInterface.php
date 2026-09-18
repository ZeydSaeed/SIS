<?php

namespace App\Application\Admission\Contracts;

interface AdmissionReadRepositoryInterface
{
    /**
     * @return array{
     *     periods: list<array<string, mixed>>,
     *     applications: list<array<string, mixed>>,
     *     documents: list<array<string, mixed>>,
     *     grade_levels: list<array{id:int, name:string}>,
     *     workflow_steps: list<array{status:int, key:string}>
     * }
     */
    public function workspace(int $schoolId, int $academicYearId): array;
}
