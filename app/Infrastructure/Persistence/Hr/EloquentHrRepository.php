<?php

namespace App\Infrastructure\Persistence\Hr;

use App\Database\SchemaHelper;
use App\Domain\Hr\Data\EmployeeSnapshot;
use App\Domain\Hr\Data\JobPositionSnapshot;
use App\Domain\Hr\Repositories\HrRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentHrRepository implements HrRepositoryInterface
{
    public function jobPositionCodeExists(int $schoolId, string $code): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('hr', 'job_positions'))
            ->where('school_id', $schoolId)
            ->where('code', $code)
            ->exists();
    }

    public function findJobPosition(int $schoolId, int $jobPositionId): ?JobPositionSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('hr', 'job_positions'))
            ->where('school_id', $schoolId)
            ->where('id', $jobPositionId)
            ->first([
                'id', 'school_id', 'code', 'name', 'category', 'status', 'created_at', 'updated_at',
            ]);

        return $row !== null ? $this->toPosition($row) : null;
    }

    public function createJobPosition(
        int $schoolId,
        string $code,
        string $name,
        int $category,
        int $status,
        string $at,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('hr', 'job_positions'))->insertGetId([
            'school_id' => $schoolId,
            'code' => $code,
            'name' => $name,
            'category' => $category,
            'status' => $status,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function listJobPositions(int $schoolId, ?int $status = null): array
    {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('hr', 'job_positions'))
            ->where('school_id', $schoolId)
            ->orderBy('id');

        if ($status !== null) {
            $q->where('status', $status);
        }

        return $q->get([
            'id', 'school_id', 'code', 'name', 'category', 'status', 'created_at', 'updated_at',
        ])->map(fn (object $row): JobPositionSnapshot => $this->toPosition($row))->all();
    }

    public function employeeNumberExists(int $schoolId, string $employeeNumber): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('hr', 'employees'))
            ->where('employee_number', $employeeNumber)
            ->exists();
    }

    public function teacherLinkExists(int $schoolId, int $teacherId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('hr', 'employees'))
            ->where('teacher_id', $teacherId)
            ->exists();
    }

    public function teacherBelongsToSchool(int $teacherId, int $schoolId, int $academicYearId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('teachers', 'teacher_schools'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->exists();
    }

    public function createEmployee(
        int $schoolId,
        string $employeeNumber,
        ?int $userId,
        ?int $teacherId,
        ?string $nationalId,
        string $firstName,
        string $lastName,
        string $fullName,
        ?string $hireDate,
        int $status,
        string $effectiveFrom,
        string $at,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('hr', 'employees'))->insertGetId([
            'employee_number' => $employeeNumber,
            'user_id' => $userId,
            'teacher_id' => $teacherId,
            'national_id' => $nationalId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'full_name' => $fullName,
            'hire_date' => $hireDate,
            'status' => $status,
            'effective_from' => $effectiveFrom,
            'effective_to' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function assignSchool(
        int $employeeId,
        int $schoolId,
        int $academicYearId,
        ?int $jobPositionId,
        bool $isPrimary,
        string $at,
    ): int {
        $this->bindSchool($schoolId);

        return (int) DB::table(SchemaHelper::qualified('hr', 'employee_schools'))->insertGetId([
            'employee_id' => $employeeId,
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'job_position_id' => $jobPositionId,
            'is_primary' => $isPrimary,
            'created_at' => $at,
        ]);
    }

    public function listEmployeesBySchool(int $schoolId, ?int $academicYearId = null, ?int $status = null): array
    {
        $this->bindSchool($schoolId);

        $q = DB::table(SchemaHelper::qualified('hr', 'employees').' as e')
            ->join(SchemaHelper::qualified('hr', 'employee_schools').' as es', 'es.employee_id', '=', 'e.id')
            ->where('es.school_id', $schoolId)
            ->orderBy('e.id');

        if ($academicYearId !== null) {
            $q->where('es.academic_year_id', $academicYearId);
        }
        if ($status !== null) {
            $q->where('e.status', $status);
        }

        return $q->get([
            'e.id',
            'e.employee_number',
            'e.user_id',
            'e.teacher_id',
            'e.national_id',
            'e.first_name',
            'e.last_name',
            'e.full_name',
            'e.hire_date',
            'e.status',
            'e.effective_from',
            'e.effective_to',
            'e.created_at',
            'e.updated_at',
            'es.job_position_id',
            'es.academic_year_id',
        ])->map(static function (object $row): EmployeeSnapshot {
            return new EmployeeSnapshot(
                id: (int) $row->id,
                employeeNumber: (string) $row->employee_number,
                userId: $row->user_id !== null ? (int) $row->user_id : null,
                teacherId: $row->teacher_id !== null ? (int) $row->teacher_id : null,
                nationalId: $row->national_id !== null ? (string) $row->national_id : null,
                firstName: (string) $row->first_name,
                lastName: (string) $row->last_name,
                fullName: (string) $row->full_name,
                hireDate: $row->hire_date !== null ? (string) $row->hire_date : null,
                status: (int) $row->status,
                effectiveFrom: (string) $row->effective_from,
                effectiveTo: $row->effective_to !== null ? (string) $row->effective_to : null,
                createdAt: (string) $row->created_at,
                updatedAt: (string) $row->updated_at,
                jobPositionId: $row->job_position_id !== null ? (int) $row->job_position_id : null,
                academicYearId: $row->academic_year_id !== null ? (int) $row->academic_year_id : null,
            );
        })->all();
    }

    private function toPosition(object $row): JobPositionSnapshot
    {
        return new JobPositionSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            code: (string) $row->code,
            name: (string) $row->name,
            category: (int) $row->category,
            status: (int) $row->status,
            createdAt: (string) $row->created_at,
            updatedAt: (string) $row->updated_at,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
