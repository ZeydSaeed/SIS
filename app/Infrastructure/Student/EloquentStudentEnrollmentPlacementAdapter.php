<?php

namespace App\Infrastructure\Student;

use App\Application\Enrollment\Services\ApplyEnrollmentPlacementChange;
use App\Database\SchemaHelper;
use App\Domain\Enrollment\Exceptions\EnrollmentNotFoundException;
use App\Domain\Enrollment\Repositories\EnrollmentRepositoryInterface;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Domain\Student\Contracts\StudentEnrollmentPlacementPort;
use Illuminate\Support\Facades\DB;

final class EloquentStudentEnrollmentPlacementAdapter implements StudentEnrollmentPlacementPort
{
    public function __construct(
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly ApplyEnrollmentPlacementChange $applyPlacement,
    ) {}

    public function activePlacement(int $studentId, int $schoolId): ?array
    {
        $row = DB::table(SchemaHelper::qualified('enrollment', 'enrollments').' as e')
            ->join(SchemaHelper::qualified('academic', 'academic_years').' as y', 'y.id', '=', 'e.academic_year_id')
            ->join(SchemaHelper::qualified('enrollment', 'classes').' as c', 'c.id', '=', 'e.class_id')
            ->leftJoin(SchemaHelper::qualified('academic', 'grade_levels').' as g', 'g.id', '=', 'c.grade_level_id')
            ->where('e.student_id', $studentId)
            ->where('e.school_id', $schoolId)
            ->where('e.status', EnrollmentStatus::ACTIVE)
            ->whereNull('e.effective_to')
            ->orderByDesc('y.start_date')
            ->orderByDesc('e.id')
            ->first(['e.id', 'e.branch_id', 'e.department_id', 'c.grade_level_id', 'g.name as grade_level_name']);

        if ($row === null) {
            return null;
        }

        return [
            'enrollment_id' => (int) $row->id,
            'branch_id' => $row->branch_id !== null ? (int) $row->branch_id : null,
            'department_id' => $row->department_id !== null ? (int) $row->department_id : null,
            'grade_level_id' => $row->grade_level_id !== null ? (int) $row->grade_level_id : null,
            'grade_level_name' => $row->grade_level_name !== null ? (string) $row->grade_level_name : null,
        ];
    }

    public function moveToDepartment(int $enrollmentId, int $schoolId, ?int $branchId, ?int $departmentId): void
    {
        $enrollment = $this->enrollments->findById($enrollmentId);
        if ($enrollment === null || $enrollment->schoolId !== $schoolId) {
            throw EnrollmentNotFoundException::forId($enrollmentId);
        }

        // Same class and section; only the branch / department moves.
        $this->applyPlacement->apply(
            enrollment: $enrollment,
            classId: $enrollment->classId,
            sectionId: $enrollment->sectionId,
            specializationId: $enrollment->specializationId,
            branchId: $branchId,
            departmentId: $departmentId,
            updatedBy: null,
        );
    }
}
