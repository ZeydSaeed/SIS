<?php

namespace App\Infrastructure\Persistence\Enrollment;

use App\Domain\Enrollment\Data\CreateEnrollmentData;
use App\Domain\Enrollment\Data\EnrollmentSnapshot;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;

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
        $record = new EnrollmentRecord;
        $record->forceFill([
            'student_id' => $data->studentId,
            'academic_year_id' => $data->academicYearId,
            'school_id' => $data->schoolId,
            'class_id' => $data->classId,
            'section_id' => $data->sectionId,
            'specialization_id' => $data->specializationId,
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
    ): void {
        $record = EnrollmentRecord::query()->findOrFail($enrollmentId);
        $record->forceFill([
            'class_id' => $classId,
            'section_id' => $sectionId,
            'specialization_id' => $specializationId,
        ]);
        $record->save();
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
        );
    }
}
