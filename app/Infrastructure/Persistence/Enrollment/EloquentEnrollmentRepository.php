<?php

namespace App\Infrastructure\Persistence\Enrollment;

use App\Domain\Enrollment\Data\CreateEnrollmentData;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;

final class EloquentEnrollmentRepository implements EnrollmentRepositoryInterface
{
    private const ACTIVE_STATUS = 1;

    public function hasActiveEnrollment(int $studentId, int $academicYearId): bool
    {
        return EnrollmentRecord::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', self::ACTIVE_STATUS)
            ->whereNull('effective_to')
            ->exists();
    }

    public function save(CreateEnrollmentData $data): int
    {
        $record = EnrollmentRecord::query()->create([
            'student_id' => $data->studentId,
            'academic_year_id' => $data->academicYearId,
            'school_id' => $data->schoolId,
            'class_id' => $data->classId,
            'section_id' => $data->sectionId,
            'specialization_id' => $data->specializationId,
            'enrollment_number' => $data->enrollmentNumber,
            'status' => self::ACTIVE_STATUS,
            'effective_from' => $data->effectiveFrom,
            'enrolled_by' => $data->enrolledBy,
        ]);

        return (int) $record->getKey();
    }

    public function generateEnrollmentNumber(int $schoolId, int $academicYearId): string
    {
        $count = EnrollmentRecord::query()
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->count();

        return sprintf('ENR-%d-%d-%06d', $schoolId, $academicYearId, $count + 1);
    }
}
