<?php

namespace App\Infrastructure\Persistence\Exams;

use App\Database\SchemaHelper;
use App\Domain\Exams\Repositories\GradeReviewRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentGradeReviewRepository implements GradeReviewRepositoryInterface
{
    public function insert(int $schoolId, int $academicYearId, int $gradeId, ?string $scoreAtRequest, bool $absentAtRequest, string $reason, ?int $requestedBy, ?string $correlationId): int
    {
        return (int) DB::table($this->table())->insertGetId([
            'school_id' => $schoolId,
            'academic_year_id' => $academicYearId,
            'grade_id' => $gradeId,
            'score_at_request' => $scoreAtRequest,
            'absent_at_request' => $absentAtRequest,
            'reason' => $reason,
            'requested_by' => $requestedBy,
            'correlation_id' => $correlationId,
        ]);
    }

    public function hasOpenFor(int $schoolId, int $gradeId): bool
    {
        return DB::table($this->table())->where('school_id', $schoolId)->where('grade_id', $gradeId)
            ->whereIn('status', ['submitted', 'under_review'])->exists();
    }

    public function lock(int $schoolId, int $reviewId): ?array
    {
        $r = DB::table($this->table())->where('id', $reviewId)->where('school_id', $schoolId)->lockForUpdate()->first();

        return $r === null ? null : [
            'id' => (int) $r->id, 'review_number' => (string) $r->review_number, 'school_id' => (int) $r->school_id,
            'academic_year_id' => (int) $r->academic_year_id, 'grade_id' => (int) $r->grade_id, 'reason' => (string) $r->reason,
            'status' => (string) $r->status, 'requested_by' => $this->int($r->requested_by), 'assigned_to' => $this->int($r->assigned_to),
            'decided_by' => $this->int($r->decided_by), 'decision_note' => $r->decision_note, 'resulting_grade_id' => $this->int($r->resulting_grade_id),
            'requested_at' => (string) $r->requested_at, 'decided_at' => $r->decided_at === null ? null : (string) $r->decided_at,
        ];
    }

    public function start(int $reviewId, int $assignedTo): void
    {
        $this->update($reviewId, ['status' => 'under_review', 'assigned_to' => $assignedTo, 'started_at' => now()]);
    }

    public function accept(int $reviewId, int $decidedBy, string $note, int $resultingGradeId): void
    {
        $this->update($reviewId, ['status' => 'accepted', 'decided_by' => $decidedBy, 'decided_at' => now(), 'decision_note' => $note, 'resulting_grade_id' => $resultingGradeId]);
    }

    public function reject(int $reviewId, int $decidedBy, string $note): void
    {
        $this->update($reviewId, ['status' => 'rejected', 'decided_by' => $decidedBy, 'decided_at' => now(), 'decision_note' => $note]);
    }

    public function withdraw(int $reviewId): void
    {
        $this->update($reviewId, ['status' => 'withdrawn']);
    }

    public function listForYear(int $schoolId, int $academicYearId, ?string $status): array
    {
        return DB::table($this->table().' as r')
            ->join(SchemaHelper::qualified('exams', 'student_grades').' as g', static function ($join): void {
                $join->on('g.id', '=', 'r.grade_id')->on('g.academic_year_id', '=', 'r.academic_year_id');
            })
            ->join(SchemaHelper::qualified('students', 'students').' as st', 'st.id', '=', 'g.student_id')
            ->join(SchemaHelper::qualified('curriculum', 'subjects').' as sub', 'sub.id', '=', 'g.subject_id')
            ->leftJoin('public.users as rq', 'rq.id', '=', 'r.requested_by')
            ->leftJoin('public.users as asg', 'asg.id', '=', 'r.assigned_to')
            ->where('r.school_id', $schoolId)->where('r.academic_year_id', $academicYearId)
            ->when($status !== null, static fn ($q) => $q->where('r.status', $status))
            ->orderByDesc('r.id')
            ->get([
                'r.id', 'r.review_number', 'r.grade_id', 'r.academic_year_id', 'r.status', 'r.reason', 'r.score_at_request', 'r.absent_at_request',
                'r.requested_at', 'r.decided_at', 'r.decision_note', 'r.requested_by', 'r.assigned_to', 'r.resulting_grade_id',
                'g.max_score', 'st.full_name as student_name', 'st.student_code', 'sub.name as subject_name',
                'rq.name as requester_name', 'asg.name as assignee_name',
            ])
            ->map(static fn (object $r): array => (array) $r)
            ->all();
    }

    /** @param  array<string, mixed>  $values */
    private function update(int $reviewId, array $values): void
    {
        DB::table($this->table())->where('id', $reviewId)->update($values + ['updated_at' => now()]);
    }

    private function int(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }

    private function table(): string
    {
        return SchemaHelper::qualified('exams', 'grade_reviews');
    }
}
