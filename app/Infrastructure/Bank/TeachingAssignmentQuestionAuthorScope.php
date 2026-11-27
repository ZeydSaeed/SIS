<?php

namespace App\Infrastructure\Bank;

use App\Application\Attendance\Contracts\TeacherDirectoryInterface;
use App\Application\Bank\Contracts\QuestionAuthorScopePort;
use App\Database\SchemaHelper;
use App\Domain\Teachers\ValueObjects\TeachingAssignmentStatus;
use Illuminate\Support\Facades\DB;

/** Authoring scope from teachers.teaching_assignments (active on today's date by the app clock), as for grade entry. */
final class TeachingAssignmentQuestionAuthorScope implements QuestionAuthorScopePort
{
    public function __construct(
        private readonly TeacherDirectoryInterface $teachers,
    ) {}

    public function subjectIdsForUser(int $userId, int $schoolId, int $academicYearId): array
    {
        $teacherId = $this->teachers->teacherIdForUser($userId, $schoolId, $academicYearId);
        if ($teacherId === null) {
            return [];
        }
        $today = now()->toDateString();

        return array_values(DB::table(SchemaHelper::qualified('teachers', 'teaching_assignments'))
            ->where('teacher_id', $teacherId)
            ->where('school_id', $schoolId)
            ->where('academic_year_id', $academicYearId)
            ->where('status', TeachingAssignmentStatus::Active)
            ->where('effective_from', '<=', $today)
            ->where(static fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $today))
            ->distinct()
            ->orderBy('subject_id')
            ->pluck('subject_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all());
    }

    public function isActiveSubject(int $subjectId): bool
    {
        return DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->where('id', $subjectId)->where('status', 1)->exists();
    }
}
