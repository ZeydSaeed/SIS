<?php

namespace App\Infrastructure\Persistence\Enrollment;

use App\Database\SchemaHelper;
use App\Domain\Enrollment\Data\CreateEnrollmentData;
use App\Domain\Enrollment\Data\EnrollmentSnapshot;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;
use Illuminate\Support\Facades\DB;

final class EloquentEnrollmentRepository implements EnrollmentRepositoryInterface
{
    public function hasActiveEnrollment(int $studentId, int $academicYearId): bool
    {
        return EnrollmentRecord::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', EnrollmentStatus::ACTIVE)
            ->whereNull('effective_to')
            ->exists();
    }

    public function findById(int $enrollmentId): ?EnrollmentSnapshot
    {
        $record = EnrollmentRecord::query()->find($enrollmentId);

        if ($record === null) {
            return null;
        }

        return $this->toSnapshot($record);
    }

    public function findByIdAndSchool(int $enrollmentId, int $schoolId): ?EnrollmentSnapshot
    {
        $record = EnrollmentRecord::query()
            ->whereKey($enrollmentId)
            ->where('school_id', $schoolId)
            ->first();

        if ($record === null) {
            return null;
        }

        return $this->toSnapshot($record);
    }

    public function save(CreateEnrollmentData $data): int
    {
        \Illuminate\Support\Facades\DB::statement(
            "SELECT set_config('app.current_school_id', ?, true)",
            [(string) $data->schoolId],
        );

        $record = new EnrollmentRecord;
        $record->forceFill([
            'student_id' => $data->studentId,
            'academic_year_id' => $data->academicYearId,
            'school_id' => $data->schoolId,
            'class_id' => $data->classId,
            'section_id' => $data->sectionId,
            'specialization_id' => $data->specializationId,
            'branch_id' => $data->branchId,
            'department_id' => $data->departmentId,
            'enrollment_number' => $data->enrollmentNumber,
            'status' => EnrollmentStatus::ACTIVE,
            'effective_from' => $data->effectiveFrom,
            'enrolled_by' => $data->enrolledBy,
        ]);
        $record->save();

        return (int) $record->getKey();
    }

    public function updatePlacement(
        int $enrollmentId,
        int $classId,
        int $sectionId,
        ?int $specializationId,
        ?int $branchId = null,
        ?int $departmentId = null,
        ?string $effectiveFrom = null,
        ?int $academicYearId = null,
        ?string $effectiveTo = null,
        bool $clearEffectiveTo = false,
        ?string $stageName = null,
        bool $updateStage = false,
        bool $syncStudentLabels = false,
    ): void {
        $record = EnrollmentRecord::query()->findOrFail($enrollmentId);
        $fill = [
            'class_id' => $classId,
            'section_id' => $sectionId,
            'specialization_id' => $specializationId,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
        ];
        if ($effectiveFrom !== null && $effectiveFrom !== '') {
            $fill['effective_from'] = $effectiveFrom;
        }
        if ($academicYearId !== null && $academicYearId > 0) {
            $fill['academic_year_id'] = $academicYearId;
        }
        if ($clearEffectiveTo) {
            $fill['effective_to'] = null;
        } elseif ($effectiveTo !== null && $effectiveTo !== '') {
            $fill['effective_to'] = $effectiveTo;
        }
        $record->forceFill($fill);
        $record->save();

        $studentFill = [];
        if ($syncStudentLabels || $branchId !== null) {
            $studentFill['branch_id'] = $branchId;
        }
        if ($syncStudentLabels || $departmentId !== null) {
            if ($departmentId !== null) {
                $departmentName = DB::table(SchemaHelper::qualified('organization', 'departments'))
                    ->where('id', $departmentId)
                    ->value('name');
                $studentFill['department_name'] = is_string($departmentName) && $departmentName !== ''
                    ? $departmentName
                    : null;
            } else {
                $studentFill['department_name'] = null;
            }
        }
        if ($syncStudentLabels || $specializationId !== null) {
            if ($specializationId !== null) {
                $specializationName = DB::table(SchemaHelper::qualified('vocational', 'specializations'))
                    ->where('id', $specializationId)
                    ->value('name');
                $studentFill['specialization_name'] = is_string($specializationName) && $specializationName !== ''
                    ? $specializationName
                    : null;
            } else {
                $studentFill['specialization_name'] = null;
            }
        }
        if ($updateStage) {
            $studentFill['stage_name'] = $stageName !== null && trim($stageName) !== ''
                ? trim($stageName)
                : null;
        }

        if ($syncStudentLabels) {
            $sectionName = DB::table(SchemaHelper::qualified('enrollment', 'sections'))
                ->where('id', $sectionId)
                ->value('name');
            $studentFill['section_name'] = is_string($sectionName) && $sectionName !== ''
                ? $sectionName
                : null;
        }

        if ($studentFill !== []) {
            DB::table(SchemaHelper::qualified('students', 'students'))
                ->where('id', (int) $record->student_id)
                ->update(array_merge($studentFill, ['updated_at' => now()]));
        }
    }

    public function updateStudentGender(int $studentId, int $gender): void
    {
        DB::table(SchemaHelper::qualified('students', 'students'))
            ->where('id', $studentId)
            ->update([
                'gender' => $gender,
                'updated_at' => now(),
            ]);
    }

    public function cancel(int $enrollmentId, string $effectiveTo): void
    {
        $record = EnrollmentRecord::query()->findOrFail($enrollmentId);
        $record->forceFill([
            'status' => EnrollmentStatus::CANCELLED,
            'effective_to' => $effectiveTo,
        ]);
        $record->save();
    }

    public function deactivate(int $enrollmentId, string $effectiveTo): void
    {
        $record = EnrollmentRecord::query()->findOrFail($enrollmentId);
        $record->forceFill([
            'status' => EnrollmentStatus::INACTIVE,
            'effective_to' => $effectiveTo,
        ]);
        $record->save();
    }

    public function reopen(int $enrollmentId): bool
    {
        return EnrollmentRecord::query()
            ->whereKey($enrollmentId)
            ->whereIn('status', [
                EnrollmentStatus::CANCELLED,
                EnrollmentStatus::INACTIVE,
                EnrollmentStatus::TRANSFERRED,
            ])
            ->update([
                'status' => EnrollmentStatus::ACTIVE,
                'effective_to' => null,
                'updated_at' => now(),
            ]) === 1;
    }

    public function setClosedStatus(int $enrollmentId, int $status, string $effectiveTo): void
    {
        if (! in_array($status, [
            EnrollmentStatus::INACTIVE,
            EnrollmentStatus::CANCELLED,
            EnrollmentStatus::TRANSFERRED,
        ], true)) {
            throw new \InvalidArgumentException('setClosedStatus requires a closed enrollment status.');
        }

        $record = EnrollmentRecord::query()->findOrFail($enrollmentId);
        $record->forceFill([
            'status' => $status,
            'effective_to' => $effectiveTo,
        ]);
        $record->save();
    }

    public function closeAsTransferred(int $enrollmentId, int $schoolId, string $effectiveTo): bool
    {
        \Illuminate\Support\Facades\DB::statement(
            "SELECT set_config('app.current_school_id', ?, true)",
            [(string) $schoolId],
        );

        return EnrollmentRecord::query()
            ->whereKey($enrollmentId)
            ->where('school_id', $schoolId)
            ->where('status', EnrollmentStatus::ACTIVE)
            ->whereNull('effective_to')
            ->update([
                'status' => EnrollmentStatus::TRANSFERRED,
                'effective_to' => $effectiveTo,
                'updated_at' => now(),
            ]) === 1;
    }

    public function generateEnrollmentNumber(int $schoolId, int $academicYearId): string
    {
        $count = EnrollmentRecord::query()
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->count();

        return sprintf('ENR-%d-%d-%06d', $schoolId, $academicYearId, $count + 1);
    }

    private function toSnapshot(EnrollmentRecord $record): EnrollmentSnapshot
    {
        return new EnrollmentSnapshot(
            id: (int) $record->getKey(),
            studentId: (int) $record->student_id,
            schoolId: (int) $record->school_id,
            academicYearId: (int) $record->academic_year_id,
            classId: (int) $record->class_id,
            sectionId: (int) $record->section_id,
            specializationId: $record->specialization_id !== null ? (int) $record->specialization_id : null,
            enrollmentNumber: (string) $record->enrollment_number,
            status: (int) $record->status,
            effectiveFrom: $record->effective_from->format('Y-m-d'),
            effectiveTo: $record->effective_to?->format('Y-m-d'),
            branchId: $record->branch_id !== null ? (int) $record->branch_id : null,
            departmentId: $record->department_id !== null ? (int) $record->department_id : null,
        );
    }
}
