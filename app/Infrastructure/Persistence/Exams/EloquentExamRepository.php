<?php

namespace App\Infrastructure\Persistence\Exams;

use App\Database\SchemaHelper;
use App\Domain\Exams\Data\CreateExamData;
use App\Domain\Exams\Data\ExamSnapshot;
use App\Domain\Exams\Repositories\ExamRepositoryInterface;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamSessionStatus;
use Illuminate\Support\Facades\DB;

final class EloquentExamRepository implements ExamRepositoryInterface
{
    public function insert(CreateExamData $data): int
    {
        return (int) DB::table($this->examsTable())->insertGetId([
            'academic_year_id' => $data->academicYearId,
            'school_id' => $data->schoolId,
            'term_id' => $data->termId,
            'exam_type_id' => $data->examTypeId,
            'name' => $data->name,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'status' => $data->status,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function findByIdAndSchool(int $examId, int $schoolId): ?ExamSnapshot
    {
        $row = DB::table($this->examsTable())
            ->where('id', $examId)
            ->where('school_id', $schoolId)
            ->first();

        return $row === null ? null : $this->toSnapshot($row);
    }

    public function lockByIdAndSchool(int $examId, int $schoolId): ?ExamSnapshot
    {
        if (SchemaHelper::isPostgreSql()) {
            $row = DB::selectOne(
                'SELECT * FROM exams.exams WHERE id = ? AND school_id = ? FOR UPDATE',
                [$examId, $schoolId],
            );

            return $row === null ? null : $this->toSnapshot($row);
        }

        return $this->findByIdAndSchool($examId, $schoolId);
    }

    public function updateAllowlisted(int $examId, int $schoolId, array $fields): void
    {
        if ($fields === []) {
            return;
        }

        $fields['updated_at'] = now();

        DB::table($this->examsTable())
            ->where('id', $examId)
            ->where('school_id', $schoolId)
            ->update($fields);
    }

    public function academicYearExists(int $academicYearId): bool
    {
        return DB::table(SchemaHelper::qualified('academic', 'academic_years'))
            ->where('id', $academicYearId)
            ->exists();
    }

    public function examTypeExists(int $examTypeId): bool
    {
        return DB::table(SchemaHelper::qualified('exams', 'exam_types'))
            ->where('id', $examTypeId)
            ->exists();
    }

    public function termBelongsToAcademicYear(int $termId, int $academicYearId): bool
    {
        return DB::table(SchemaHelper::qualified('academic', 'terms'))
            ->where('id', $termId)
            ->where('academic_year_id', $academicYearId)
            ->exists();
    }

    public function hasCurrentGradeForExam(int $examId, int $schoolId): bool
    {
        $grades = SchemaHelper::qualified('exams', 'student_grades');
        $enrollments = SchemaHelper::qualified('exams', 'exam_enrollments');
        $sessions = SchemaHelper::qualified('exams', 'exam_sessions');

        return DB::table("{$grades} as g")
            ->join("{$enrollments} as ee", 'ee.id', '=', 'g.exam_enrollment_id')
            ->join("{$sessions} as es", 'es.id', '=', 'ee.exam_session_id')
            ->where('es.exam_id', $examId)
            ->where('g.school_id', $schoolId)
            ->where('g.is_current', true)
            ->exists();
    }

    public function hasCompletedSessionForExam(int $examId, int $schoolId): bool
    {
        return DB::table($this->sessionsTable())
            ->where('exam_id', $examId)
            ->where('school_id', $schoolId)
            ->where('status', ExamSessionStatus::Completed->value)
            ->exists();
    }

    public function sessionStatusCounts(int $examId, int $schoolId): array
    {
        $rows = DB::table($this->sessionsTable())
            ->select('status', DB::raw('COUNT(*) as c'))
            ->where('exam_id', $examId)
            ->where('school_id', $schoolId)
            ->groupBy('status')
            ->get();

        $counts = [
            'total' => 0,
            'scheduled' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'cancelled' => 0,
        ];

        foreach ($rows as $row) {
            $c = (int) $row->c;
            $counts['total'] += $c;
            match ((int) $row->status) {
                ExamSessionStatus::Scheduled->value => $counts['scheduled'] += $c,
                ExamSessionStatus::InProgress->value => $counts['in_progress'] += $c,
                ExamSessionStatus::Completed->value => $counts['completed'] += $c,
                ExamSessionStatus::Cancelled->value => $counts['cancelled'] += $c,
                default => null,
            };
        }

        return $counts;
    }

    public function cancelOpenSessionsForExam(int $examId, int $schoolId): array
    {
        $open = [
            ExamSessionStatus::Scheduled->value,
            ExamSessionStatus::InProgress->value,
        ];

        $rows = DB::table($this->sessionsTable())
            ->where('exam_id', $examId)
            ->where('school_id', $schoolId)
            ->whereIn('status', $open)
            ->select(['id', 'status'])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'id' => (int) $row->id,
                'previous_status' => (int) $row->status,
            ];
        }

        if ($result !== []) {
            DB::table($this->sessionsTable())
                ->where('exam_id', $examId)
                ->where('school_id', $schoolId)
                ->whereIn('status', $open)
                ->update(['status' => ExamSessionStatus::Cancelled->value]);
        }

        return $result;
    }

    public function withdrawActiveEnrollmentsForExam(int $examId, int $schoolId): array
    {
        $active = [
            ExamEnrollmentStatus::Registered->value,
            ExamEnrollmentStatus::Confirmed->value,
            ExamEnrollmentStatus::Present->value,
        ];

        $enrollments = SchemaHelper::qualified('exams', 'exam_enrollments');
        $sessions = SchemaHelper::qualified('exams', 'exam_sessions');

        $rows = DB::table("{$enrollments} as ee")
            ->join("{$sessions} as es", 'es.id', '=', 'ee.exam_session_id')
            ->where('es.exam_id', $examId)
            ->where('ee.school_id', $schoolId)
            ->whereIn('ee.status', $active)
            ->select(['ee.id', 'ee.exam_session_id', 'ee.status'])
            ->get();

        $result = [];
        $ids = [];
        foreach ($rows as $row) {
            $id = (int) $row->id;
            $ids[] = $id;
            $result[] = [
                'id' => $id,
                'exam_session_id' => (int) $row->exam_session_id,
                'previous_status' => (int) $row->status,
            ];
        }

        if ($ids !== []) {
            DB::table($enrollments)
                ->whereIn('id', $ids)
                ->where('school_id', $schoolId)
                ->update(['status' => ExamEnrollmentStatus::Withdrawn->value]);
        }

        return $result;
    }

    private function examsTable(): string
    {
        return SchemaHelper::qualified('exams', 'exams');
    }

    private function sessionsTable(): string
    {
        return SchemaHelper::qualified('exams', 'exam_sessions');
    }

    private function toSnapshot(object $row): ExamSnapshot
    {
        return new ExamSnapshot(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            academicYearId: (int) $row->academic_year_id,
            termId: (int) $row->term_id,
            examTypeId: (int) $row->exam_type_id,
            name: (string) $row->name,
            startDate: (string) $row->start_date,
            endDate: (string) $row->end_date,
            status: (int) $row->status,
        );
    }
}
