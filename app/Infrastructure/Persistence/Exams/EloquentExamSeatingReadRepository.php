<?php

namespace App\Infrastructure\Persistence\Exams;

use App\Application\Exams\Contracts\ExamSeatingReadRepositoryInterface;
use App\Database\SchemaHelper;
use App\Domain\Enrollment\ValueObjects\EnrollmentStatus;
use App\Domain\Exams\ValueObjects\ExamEnrollmentStatus;
use Illuminate\Support\Facades\DB;

final class EloquentExamSeatingReadRepository implements ExamSeatingReadRepositoryInterface
{
    private const ACTIVE = 1;

    public function classes(int $schoolId, int $academicYearId): array
    {
        $classes = DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->where('status', self::ACTIVE)
            ->orderBy('name')->orderBy('id')->get(['id', 'name']);
        if ($classes->isEmpty()) {
            return [];
        }

        $sections = DB::table(SchemaHelper::qualified('enrollment', 'sections'))
            ->whereIn('class_id', $classes->pluck('id'))->where('status', self::ACTIVE)
            ->orderBy('name')->orderBy('id')->get(['id', 'class_id', 'name'])
            ->groupBy('class_id');

        return $classes->map(static fn (object $c): array => [
            'id' => (int) $c->id,
            'name' => (string) $c->name,
            'sections' => ($sections[$c->id] ?? collect())->map(static fn (object $s): array => ['id' => (int) $s->id, 'name' => (string) $s->name])->values()->all(),
        ])->all();
    }

    public function classInSchoolYear(int $classId, int $schoolId, int $academicYearId): bool
    {
        return DB::table(SchemaHelper::qualified('enrollment', 'classes'))
            ->where('id', $classId)->where('school_id', $schoolId)->where('academic_year_id', $academicYearId)->exists();
    }

    public function sectionInClass(int $sectionId, int $classId): bool
    {
        return DB::table(SchemaHelper::qualified('enrollment', 'sections'))->where('id', $sectionId)->where('class_id', $classId)->exists();
    }

    public function candidates(int $schoolId, int $academicYearId, int $classId, ?int $sectionId, int $examSessionId, int $subjectId): array
    {
        $subjects = SchemaHelper::qualified('enrollment', 'enrollment_subjects');
        $seats = SchemaHelper::qualified('exams', 'exam_enrollments');

        return DB::table(SchemaHelper::qualified('enrollment', 'enrollments').' as e')
            ->where('e.school_id', $schoolId)
            ->where('e.academic_year_id', $academicYearId)
            ->where('e.class_id', $classId)
            ->when($sectionId !== null, static fn ($q) => $q->where('e.section_id', $sectionId))
            ->where('e.status', EnrollmentStatus::ACTIVE)
            ->whereNull('e.effective_to')
            ->orderBy('e.id')
            ->select('e.id')
            ->selectRaw("EXISTS (SELECT 1 FROM {$subjects} es WHERE es.enrollment_id = e.id AND es.subject_id = ? AND es.status = ?) AS takes_subject", [$subjectId, self::ACTIVE])
            ->selectRaw("EXISTS (SELECT 1 FROM {$seats} ee WHERE ee.enrollment_id = e.id AND ee.exam_session_id = ?) AS seated", [$examSessionId])
            ->get()
            ->map(static fn (object $r): array => [
                'enrollment_id' => (int) $r->id,
                'takes_subject' => (bool) $r->takes_subject,
                'seated' => (bool) $r->seated,
            ])
            ->all();
    }

    public function activeSeatCounts(int $schoolId, array $examSessionIds): array
    {
        if ($examSessionIds === []) {
            return [];
        }
        $active = array_values(array_map(
            static fn (ExamEnrollmentStatus $s): int => $s->value,
            array_filter(ExamEnrollmentStatus::cases(), static fn (ExamEnrollmentStatus $s): bool => $s->isActiveSeat()),
        ));

        return DB::table(SchemaHelper::qualified('exams', 'exam_enrollments'))
            ->where('school_id', $schoolId)->whereIn('exam_session_id', $examSessionIds)->whereIn('status', $active)
            ->groupBy('exam_session_id')->selectRaw('exam_session_id, count(*) AS n')
            ->pluck('n', 'exam_session_id')
            ->map(static fn ($n): int => (int) $n)
            ->all();
    }
}
