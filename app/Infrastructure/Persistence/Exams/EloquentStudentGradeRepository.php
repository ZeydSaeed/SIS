<?php

namespace App\Infrastructure\Persistence\Exams;

use App\Database\SchemaHelper;
use App\Domain\Exams\Data\CreateStudentGradeData;
use App\Domain\Exams\Data\ExamEnrollmentGradeContext;
use App\Domain\Exams\Data\StudentGradeSnapshot;
use App\Domain\Exams\Exceptions\CurrentGradeAlreadyExistsException;
use App\Domain\Exams\Repositories\StudentGradeRepositoryInterface;
use App\Domain\Exams\ValueObjects\GradeStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class EloquentStudentGradeRepository implements StudentGradeRepositoryInterface
{
    public function findExamEnrollmentContext(int $examEnrollmentId, int $schoolId): ?ExamEnrollmentGradeContext
    {
        $ee = SchemaHelper::qualified('exams', 'exam_enrollments');
        $es = SchemaHelper::qualified('exams', 'exam_sessions');
        $ex = SchemaHelper::qualified('exams', 'exams');
        $en = SchemaHelper::qualified('enrollment', 'enrollments');

        $row = DB::table("{$ee} as ee")
            ->join("{$es} as es", 'es.id', '=', 'ee.exam_session_id')
            ->join("{$ex} as ex", 'ex.id', '=', 'es.exam_id')
            ->join("{$en} as en", 'en.id', '=', 'ee.enrollment_id')
            ->where('ee.id', $examEnrollmentId)
            ->where('ee.school_id', $schoolId)
            ->select([
                'ee.id as exam_enrollment_id',
                'ee.school_id',
                'ee.exam_session_id',
                'ee.enrollment_id',
                'ee.status as exam_enrollment_status',
                'es.subject_id',
                'es.status as session_status',
                'es.max_grade',
                'ex.id as exam_id',
                'ex.status as exam_status',
                'ex.academic_year_id',
                'en.student_id',
                'en.status as academic_enrollment_status',
                'en.effective_to',
            ])
            ->first();

        if ($row === null) {
            return null;
        }

        return new ExamEnrollmentGradeContext(
            examEnrollmentId: (int) $row->exam_enrollment_id,
            schoolId: (int) $row->school_id,
            examSessionId: (int) $row->exam_session_id,
            enrollmentId: (int) $row->enrollment_id,
            studentId: (int) $row->student_id,
            subjectId: (int) $row->subject_id,
            academicYearId: (int) $row->academic_year_id,
            examId: (int) $row->exam_id,
            examStatus: (int) $row->exam_status,
            sessionStatus: (int) $row->session_status,
            examEnrollmentStatus: (int) $row->exam_enrollment_status,
            academicEnrollmentStatus: (int) $row->academic_enrollment_status,
            academicEnrollmentEffectiveTo: $row->effective_to !== null ? (string) $row->effective_to : null,
            maxScore: (string) $row->max_grade,
        );
    }

    public function findByIdentity(int $gradeId, int $academicYearId, int $schoolId): ?StudentGradeSnapshot
    {
        $row = DB::table($this->table())
            ->where('id', $gradeId)
            ->where('academic_year_id', $academicYearId)
            ->where('school_id', $schoolId)
            ->first();

        return $row === null ? null : $this->toSnapshot($row);
    }

    public function findCurrentForExamEnrollment(int $examEnrollmentId, int $academicYearId, int $schoolId): ?StudentGradeSnapshot
    {
        $row = DB::table($this->table())
            ->where('exam_enrollment_id', $examEnrollmentId)
            ->where('academic_year_id', $academicYearId)
            ->where('school_id', $schoolId)
            ->where('is_current', true)
            ->first();

        return $row === null ? null : $this->toSnapshot($row);
    }

    public function lockCurrentForExamEnrollment(int $examEnrollmentId, int $academicYearId, int $schoolId): ?StudentGradeSnapshot
    {
        if (SchemaHelper::isPostgreSql()) {
            $row = DB::selectOne(
                'SELECT * FROM exams.student_grades
                 WHERE exam_enrollment_id = ? AND academic_year_id = ? AND school_id = ? AND is_current = true
                 FOR UPDATE',
                [$examEnrollmentId, $academicYearId, $schoolId],
            );

            return $row === null ? null : $this->toSnapshot($row);
        }

        return $this->findCurrentForExamEnrollment($examEnrollmentId, $academicYearId, $schoolId);
    }

    public function lockByIdentity(int $gradeId, int $academicYearId, int $schoolId): ?StudentGradeSnapshot
    {
        if (SchemaHelper::isPostgreSql()) {
            $row = DB::selectOne(
                'SELECT * FROM exams.student_grades
                 WHERE id = ? AND academic_year_id = ? AND school_id = ?
                 FOR UPDATE',
                [$gradeId, $academicYearId, $schoolId],
            );

            return $row === null ? null : $this->toSnapshot($row);
        }

        return $this->findByIdentity($gradeId, $academicYearId, $schoolId);
    }

    public function insert(CreateStudentGradeData $data): int
    {
        try {
            return (int) DB::table($this->table())->insertGetId([
                'academic_year_id' => $data->academicYearId,
                'school_id' => $data->schoolId,
                'exam_enrollment_id' => $data->examEnrollmentId,
                'exam_session_id' => $data->examSessionId,
                'enrollment_id' => $data->enrollmentId,
                'student_id' => $data->studentId,
                'subject_id' => $data->subjectId,
                'score' => $data->score,
                'max_score' => $data->maxScore,
                'is_absent' => $data->isAbsent,
                'status' => $data->status,
                'is_current' => $data->isCurrent,
                'correction_of_grade_id' => $data->correctionOfGradeId,
                'entered_by' => $data->enteredBy,
                'entered_at' => $data->enteredAt,
                'finalized_at' => $data->finalizedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueCurrentViolation($e)) {
                throw CurrentGradeAlreadyExistsException::forExamEnrollment($data->examEnrollmentId);
            }

            throw $e;
        }
    }

    private function isUniqueCurrentViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'student_grades_current_enrollment_uidx')
            || str_contains($message, 'UNIQUE constraint failed');
    }

    public function markVoided(int $gradeId, int $academicYearId): void
    {
        DB::table($this->table())
            ->where('id', $gradeId)
            ->where('academic_year_id', $academicYearId)
            ->update([
                'is_current' => false,
                'status' => GradeStatus::Voided->value,
                'updated_at' => now(),
            ]);
    }

    public function markFinalized(int $gradeId, int $academicYearId, string $finalizedAt): void
    {
        DB::table($this->table())
            ->where('id', $gradeId)
            ->where('academic_year_id', $academicYearId)
            ->update([
                'status' => GradeStatus::Finalized->value,
                'finalized_at' => $finalizedAt,
                'updated_at' => now(),
            ]);
    }

    public function correctionChainIds(int $gradeId, int $academicYearId, int $maxDepth = 32): array
    {
        $ids = [];
        $currentId = $gradeId;

        for ($i = 0; $i < $maxDepth; $i++) {
            $row = DB::table($this->table())
                ->where('id', $currentId)
                ->where('academic_year_id', $academicYearId)
                ->first(['id', 'correction_of_grade_id']);

            if ($row === null) {
                break;
            }

            $ids[] = (int) $row->id;
            if ($row->correction_of_grade_id === null) {
                break;
            }

            $currentId = (int) $row->correction_of_grade_id;
        }

        return $ids;
    }

    private function table(): string
    {
        return SchemaHelper::qualified('exams', 'student_grades');
    }

    private function toSnapshot(object $row): StudentGradeSnapshot
    {
        return new StudentGradeSnapshot(
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
            enteredBy: $row->entered_by !== null ? (int) $row->entered_by : null,
            enteredAt: (string) $row->entered_at,
            finalizedAt: $row->finalized_at !== null ? (string) $row->finalized_at : null,
        );
    }
}
