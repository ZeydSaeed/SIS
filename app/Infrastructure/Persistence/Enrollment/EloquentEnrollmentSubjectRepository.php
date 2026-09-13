<?php

namespace App\Infrastructure\Persistence\Enrollment;

use App\Database\SchemaHelper;
use App\Domain\Enrollment\Data\EnrollmentSubjectSnapshot;
use App\Domain\Enrollment\Repositories\EnrollmentSubjectRepositoryInterface;
use App\Domain\Enrollment\ValueObjects\EnrollmentSubjectStatus;
use Illuminate\Support\Facades\DB;

final class EloquentEnrollmentSubjectRepository implements EnrollmentSubjectRepositoryInterface
{
    public function studentHasSubjectHistory(int $schoolId, int $studentId, int $subjectId): bool
    {
        $this->bindSchool($schoolId);

        return DB::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects').' as es')
            ->join(SchemaHelper::qualified('enrollment', 'enrollments').' as e', 'e.id', '=', 'es.enrollment_id')
            ->where('e.student_id', $studentId)
            ->where('e.school_id', $schoolId)
            ->where('es.subject_id', $subjectId)
            ->exists();
    }

    public function assignOrReactivate(
        int $schoolId,
        int $enrollmentId,
        int $subjectId,
        bool $isElective,
        string $createdAt,
    ): int {
        $this->bindSchool($schoolId);

        $existing = DB::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects'))
            ->where('enrollment_id', $enrollmentId)
            ->where('subject_id', $subjectId)
            ->first(['id', 'status']);

        if ($existing !== null) {
            if ((int) $existing->status !== EnrollmentSubjectStatus::Active->value) {
                DB::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects'))
                    ->where('id', (int) $existing->id)
                    ->update([
                        'status' => EnrollmentSubjectStatus::Active->value,
                        'is_elective' => $isElective,
                    ]);
            }

            return (int) $existing->id;
        }

        return (int) DB::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects'))->insertGetId([
            'enrollment_id' => $enrollmentId,
            'subject_id' => $subjectId,
            'is_elective' => $isElective,
            'status' => EnrollmentSubjectStatus::Active->value,
            'created_at' => $createdAt,
        ]);
    }

    public function findActive(int $schoolId, int $linkId): ?EnrollmentSubjectSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects').' as es')
            ->join(SchemaHelper::qualified('enrollment', 'enrollments').' as e', 'e.id', '=', 'es.enrollment_id')
            ->where('es.id', $linkId)
            ->where('e.school_id', $schoolId)
            ->where('es.status', EnrollmentSubjectStatus::Active->value)
            ->first(['es.id', 'es.enrollment_id', 'es.subject_id', 'es.is_elective', 'es.status']);

        return $row === null ? null : $this->map($row);
    }

    public function findInactive(int $schoolId, int $linkId): ?EnrollmentSubjectSnapshot
    {
        $this->bindSchool($schoolId);

        $row = DB::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects').' as es')
            ->join(SchemaHelper::qualified('enrollment', 'enrollments').' as e', 'e.id', '=', 'es.enrollment_id')
            ->where('es.id', $linkId)
            ->where('e.school_id', $schoolId)
            ->where('es.status', EnrollmentSubjectStatus::Inactive->value)
            ->first(['es.id', 'es.enrollment_id', 'es.subject_id', 'es.is_elective', 'es.status']);

        return $row === null ? null : $this->map($row);
    }

    public function listActive(int $schoolId, int $enrollmentId): array
    {
        $this->bindSchool($schoolId);

        $rows = DB::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects'))
            ->where('enrollment_id', $enrollmentId)
            ->where('status', EnrollmentSubjectStatus::Active->value)
            ->orderBy('id')
            ->get(['id', 'enrollment_id', 'subject_id', 'is_elective', 'status']);

        return $rows->map(fn ($row): EnrollmentSubjectSnapshot => $this->map($row))->all();
    }

    public function deactivate(int $schoolId, int $linkId): bool
    {
        $this->bindSchool($schoolId);

        if ($this->findActive($schoolId, $linkId) === null) {
            return false;
        }

        $updated = DB::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects'))
            ->where('id', $linkId)
            ->where('status', EnrollmentSubjectStatus::Active->value)
            ->update(['status' => EnrollmentSubjectStatus::Inactive->value]);

        return $updated > 0;
    }

    public function reactivate(int $schoolId, int $linkId): bool
    {
        $this->bindSchool($schoolId);

        if ($this->findInactive($schoolId, $linkId) === null) {
            return false;
        }

        $updated = DB::table(SchemaHelper::qualified('enrollment', 'enrollment_subjects'))
            ->where('id', $linkId)
            ->where('status', EnrollmentSubjectStatus::Inactive->value)
            ->update(['status' => EnrollmentSubjectStatus::Active->value]);

        return $updated > 0;
    }

    private function map(object $row): EnrollmentSubjectSnapshot
    {
        return new EnrollmentSubjectSnapshot(
            id: (int) $row->id,
            enrollmentId: (int) $row->enrollment_id,
            subjectId: (int) $row->subject_id,
            isElective: (bool) $row->is_elective,
            status: (int) $row->status,
        );
    }

    private function bindSchool(int $schoolId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
    }
}
