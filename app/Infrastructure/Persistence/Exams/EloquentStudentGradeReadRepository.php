<?php

namespace App\Infrastructure\Persistence\Exams;

use App\Application\Exams\Contracts\StudentGradeReadRepositoryInterface;
use App\Application\Exams\DTOs\StudentGradeDTO;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

final class EloquentStudentGradeReadRepository implements StudentGradeReadRepositoryInterface
{
    public function findByIdentity(int $gradeId, int $academicYearId, int $schoolId): ?StudentGradeDTO
    {
        $row = DB::table($this->table())
            ->where('id', $gradeId)
            ->where('academic_year_id', $academicYearId)
            ->where('school_id', $schoolId)
            ->first();

        return $row === null ? null : $this->toDto($row);
    }

    public function findCurrentForExamEnrollment(int $examEnrollmentId, int $academicYearId, int $schoolId): ?StudentGradeDTO
    {
        $row = DB::table($this->table())
            ->where('exam_enrollment_id', $examEnrollmentId)
            ->where('academic_year_id', $academicYearId)
            ->where('school_id', $schoolId)
            ->where('is_current', true)
            ->first();

        return $row === null ? null : $this->toDto($row);
    }

    public function listForExamSession(int $examSessionId, int $academicYearId, int $schoolId): array
    {
        return DB::table($this->table())
            ->where('exam_session_id', $examSessionId)
            ->where('academic_year_id', $academicYearId)
            ->where('school_id', $schoolId)
            ->where('is_current', true)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => $this->toDto($row))
            ->all();
    }

    public function listForEnrollment(int $enrollmentId, int $academicYearId, int $schoolId): array
    {
        return DB::table($this->table())
            ->where('enrollment_id', $enrollmentId)
            ->where('academic_year_id', $academicYearId)
            ->where('school_id', $schoolId)
            ->orderBy('id')
            ->get()
            ->map(fn ($row) => $this->toDto($row))
            ->all();
    }

    private function table(): string
    {
        return SchemaHelper::qualified('exams', 'student_grades');
    }

    private function toDto(object $row): StudentGradeDTO
    {
        return new StudentGradeDTO(
            id: (int) $row->id,
            academicYearId: (int) $row->academic_year_id,
            schoolId: (int) $row->school_id,
            examEnrollmentId: (int) $row->exam_enrollment_id,
            examSessionId: (int) $row->exam_session_id,
            enrollmentId: (int) $row->enrollment_id,
            studentId: (int) $row->student_id,
            subjectId: (int) $row->subject_id,
            score: $row->score !== null ? (string) $row->score : null,
            maxScore: (string) $row->max_score,
            isAbsent: (bool) $row->is_absent,
            status: (int) $row->status,
            isCurrent: (bool) $row->is_current,
            correctionOfGradeId: $row->correction_of_grade_id !== null ? (int) $row->correction_of_grade_id : null,
            enteredAt: (string) $row->entered_at,
            finalizedAt: $row->finalized_at !== null ? (string) $row->finalized_at : null,
        );
    }
}
