<?php

namespace App\Infrastructure\Persistence\Enrollment;

use App\Application\Enrollment\Contracts\EnrollmentReadRepositoryInterface;
use App\Application\Enrollment\DTOs\EnrollmentDTO;
use App\Infrastructure\Persistence\Eloquent\EnrollmentRecord;

final class EloquentEnrollmentReadRepository implements EnrollmentReadRepositoryInterface
{
    public function findDetail(int $enrollmentId, int $schoolId): ?EnrollmentDTO
    {
        $record = EnrollmentRecord::query()
            ->whereKey($enrollmentId)
            ->where('school_id', $schoolId)
            ->first();

        if ($record === null) {
            return null;
        }

        return $this->toDto($record);
    }

    public function paginate(int $schoolId, ?int $academicYearId, int $page, int $perPage): array
    {
        $query = EnrollmentRecord::query()->where('school_id', $schoolId);

        if ($academicYearId !== null) {
            $query->where('academic_year_id', $academicYearId);
        }

        $total = (clone $query)->count();
        $records = $query
            ->orderByDesc('id')
            ->forPage($page, $perPage)
            ->get();

        return [
            'items' => $records->map(fn (EnrollmentRecord $record): EnrollmentDTO => $this->toDto($record))->all(),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    private function toDto(EnrollmentRecord $record): EnrollmentDTO
    {
        return new EnrollmentDTO(
            id: (int) $record->getKey(),
            studentId: (int) $record->student_id,
            schoolId: (int) $record->school_id,
            academicYearId: (int) $record->academic_year_id,
            classId: (int) $record->class_id,
            sectionId: (int) $record->section_id,
            enrollmentNumber: (string) $record->enrollment_number,
            status: (int) $record->status,
            effectiveFrom: $record->effective_from->format('Y-m-d'),
            effectiveTo: $record->effective_to?->format('Y-m-d'),
        );
    }
}
