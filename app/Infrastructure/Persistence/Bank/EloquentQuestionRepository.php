<?php

namespace App\Infrastructure\Persistence\Bank;

use App\Database\SchemaHelper;
use App\Domain\Bank\Repositories\QuestionRepositoryInterface;
use App\Domain\Bank\ValueObjects\QuestionContent;
use Illuminate\Support\Facades\DB;

final class EloquentQuestionRepository implements QuestionRepositoryInterface
{
    private const VERSION_COLUMNS = ['id', 'question_id', 'school_id', 'version_no', 'type', 'stem', 'payload', 'max_mark', 'status', 'created_by'];

    public function insertQuestion(int $schoolId, int $subjectId, int $createdBy): int
    {
        return (int) DB::table($this->questions())->insertGetId([
            'school_id' => $schoolId,
            'subject_id' => $subjectId,
            'created_by' => $createdBy,
        ]);
    }

    public function insertVersion(int $questionId, int $schoolId, int $versionNo, QuestionContent $content, int $createdBy): int
    {
        return (int) DB::table($this->versions())->insertGetId([
            'question_id' => $questionId,
            'school_id' => $schoolId,
            'version_no' => $versionNo,
            'type' => $content->type->value,
            'stem' => $content->stem,
            'payload' => json_encode($content->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'max_mark' => $content->maxMark,
            'created_by' => $createdBy,
        ]);
    }

    public function lockQuestion(int $schoolId, int $questionId): ?array
    {
        $q = DB::table($this->questions())->where('id', $questionId)->where('school_id', $schoolId)->lockForUpdate()
            ->first(['id', 'school_id', 'subject_id', 'status', 'created_by']);

        return $q === null ? null : [
            'id' => (int) $q->id,
            'school_id' => (int) $q->school_id,
            'subject_id' => (int) $q->subject_id,
            'status' => (string) $q->status,
            'created_by' => $q->created_by !== null ? (int) $q->created_by : null,
        ];
    }

    public function lockVersion(int $schoolId, int $versionId): ?array
    {
        $v = DB::table($this->versions())->where('id', $versionId)->where('school_id', $schoolId)->lockForUpdate()
            ->first(self::VERSION_COLUMNS);

        return $v === null ? null : $this->version($v);
    }

    public function latestVersion(int $schoolId, int $questionId): ?array
    {
        $v = DB::table($this->versions())->where('question_id', $questionId)->where('school_id', $schoolId)
            ->orderByDesc('version_no')->first(self::VERSION_COLUMNS);

        return $v === null ? null : $this->version($v);
    }

    public function hasOpenVersion(int $schoolId, int $questionId): bool
    {
        return DB::table($this->versions())->where('question_id', $questionId)->where('school_id', $schoolId)
            ->whereIn('status', ['draft', 'in_review'])->exists();
    }

    public function approvedVersionId(int $schoolId, int $questionId): ?int
    {
        $id = DB::table($this->versions())->where('question_id', $questionId)->where('school_id', $schoolId)
            ->where('status', 'approved')->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function updateDraft(int $versionId, QuestionContent $content): void
    {
        $this->updateVersion($versionId, [
            'type' => $content->type->value,
            'stem' => $content->stem,
            'payload' => json_encode($content->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'max_mark' => $content->maxMark,
        ]);
    }

    public function submit(int $versionId, int $submittedBy): void
    {
        $this->updateVersion($versionId, ['status' => 'in_review', 'submitted_by' => $submittedBy, 'submitted_at' => now()]);
    }

    public function approve(int $versionId, int $decidedBy, string $note): void
    {
        $this->updateVersion($versionId, ['status' => 'approved', 'decided_by' => $decidedBy, 'decided_at' => now(), 'decision_note' => $note === '' ? null : $note]);
    }

    public function reject(int $versionId, int $decidedBy, string $note): void
    {
        $this->updateVersion($versionId, ['status' => 'rejected', 'decided_by' => $decidedBy, 'decided_at' => now(), 'decision_note' => $note]);
    }

    public function supersede(int $versionId): void
    {
        $this->updateVersion($versionId, ['status' => 'superseded']);
    }

    public function retire(int $questionId, int $retiredBy, string $reason): void
    {
        DB::table($this->questions())->where('id', $questionId)->update([
            'status' => 'retired',
            'retired_by' => $retiredBy,
            'retired_at' => now(),
            'retire_reason' => $reason,
            'updated_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $values */
    private function updateVersion(int $versionId, array $values): void
    {
        DB::table($this->versions())->where('id', $versionId)->update($values + ['updated_at' => now()]);
    }

    /** @return array{id: int, question_id: int, school_id: int, version_no: int, type: string, stem: string, payload: array<string, mixed>, max_mark: string, status: string, created_by: int} */
    private function version(\stdClass $v): array
    {
        $payload = json_decode((string) $v->payload, true, 32, JSON_THROW_ON_ERROR);

        return [
            'id' => (int) $v->id,
            'question_id' => (int) $v->question_id,
            'school_id' => (int) $v->school_id,
            'version_no' => (int) $v->version_no,
            'type' => (string) $v->type,
            'stem' => (string) $v->stem,
            'payload' => is_array($payload) ? $payload : [],
            'max_mark' => (string) $v->max_mark,
            'status' => (string) $v->status,
            'created_by' => (int) $v->created_by,
        ];
    }

    private function questions(): string
    {
        return SchemaHelper::qualified('bank', 'questions');
    }

    private function versions(): string
    {
        return SchemaHelper::qualified('bank', 'question_versions');
    }
}
