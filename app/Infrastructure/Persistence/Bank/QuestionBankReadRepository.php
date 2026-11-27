<?php

namespace App\Infrastructure\Persistence\Bank;

use App\Application\Bank\Contracts\QuestionBankReadRepositoryInterface;
use App\Database\SchemaHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class QuestionBankReadRepository implements QuestionBankReadRepositoryInterface
{
    public function paginate(int $schoolId, ?array $subjectIds, ?int $subjectId, ?string $status, ?string $search, int $page, int $perPage): array
    {
        if ($subjectIds === []) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $query = DB::table(SchemaHelper::qualified('bank', 'questions').' as q')
            // The latest version of each question (highest version_no).
            ->join(SchemaHelper::qualified('bank', 'question_versions').' as v', static function ($join): void {
                $join->on('v.question_id', '=', 'q.id')->on('v.school_id', '=', 'q.school_id');
            })
            ->whereNotExists(static function (Builder $q): void {
                $q->selectRaw('1')->from(SchemaHelper::qualified('bank', 'question_versions').' as nv')
                    ->whereColumn('nv.question_id', 'v.question_id')->whereColumn('nv.version_no', '>', 'v.version_no');
            })
            ->join(SchemaHelper::qualified('curriculum', 'subjects').' as s', 's.id', '=', 'q.subject_id')
            ->leftJoin('public.users as u', 'u.id', '=', 'v.created_by')
            ->where('q.school_id', $schoolId)
            ->when($subjectIds !== null, static fn (Builder $q) => $q->whereIn('q.subject_id', $subjectIds))
            ->when($subjectId !== null, static fn (Builder $q) => $q->where('q.subject_id', $subjectId))
            ->when($status === 'retired', static fn (Builder $q) => $q->where('q.status', 'retired'))
            ->when($status !== null && $status !== 'retired', static fn (Builder $q) => $q->where('q.status', 'active')->where('v.status', $status))
            ->when($search !== null, static function (Builder $q) use ($search): void {
                $q->where('v.stem', 'ilike', '%'.addcslashes((string) $search, '%_\\').'%');
            });

        $total = (clone $query)->count();
        $rows = $query->orderByDesc('q.id')
            ->offset(($page - 1) * $perPage)->limit($perPage)
            ->get([
                'q.id', 'q.subject_id', 'q.status as question_status', 's.name as subject_name', 's.name_en as subject_name_en',
                'v.id as version_id', 'v.version_no', 'v.type', 'v.status as version_status', 'v.max_mark', 'v.created_by',
                'u.name as author_name', 'v.updated_at', DB::raw('left(v.stem, 160) as stem_excerpt'),
            ])
            ->map(static fn (object $r): array => [
                'id' => (int) $r->id,
                'subject_id' => (int) $r->subject_id,
                'subject_name' => (string) $r->subject_name,
                'subject_name_en' => $r->subject_name_en,
                'question_status' => (string) $r->question_status,
                'version_id' => (int) $r->version_id,
                'version_no' => (int) $r->version_no,
                'type' => (string) $r->type,
                'version_status' => (string) $r->version_status,
                'max_mark' => (string) $r->max_mark,
                'created_by' => (int) $r->created_by,
                'author_name' => $r->author_name,
                'stem_excerpt' => (string) $r->stem_excerpt,
                'updated_at' => self::instant($r->updated_at),
            ])
            ->all();

        return ['data' => array_values($rows), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function find(int $schoolId, int $questionId): ?array
    {
        $q = DB::table(SchemaHelper::qualified('bank', 'questions').' as q')
            ->join(SchemaHelper::qualified('curriculum', 'subjects').' as s', 's.id', '=', 'q.subject_id')
            ->leftJoin('public.users as ru', 'ru.id', '=', 'q.retired_by')
            ->where('q.id', $questionId)->where('q.school_id', $schoolId)
            ->first([
                'q.id', 'q.subject_id', 'q.status', 'q.created_by', 'q.retired_at', 'q.retire_reason', 'q.created_at',
                's.name as subject_name', 's.name_en as subject_name_en', 'ru.name as retired_by_name',
            ]);
        if ($q === null) {
            return null;
        }

        $versions = DB::table(SchemaHelper::qualified('bank', 'question_versions').' as v')
            ->leftJoin('public.users as cu', 'cu.id', '=', 'v.created_by')
            ->leftJoin('public.users as su', 'su.id', '=', 'v.submitted_by')
            ->leftJoin('public.users as du', 'du.id', '=', 'v.decided_by')
            ->where('v.question_id', $questionId)->where('v.school_id', $schoolId)
            ->orderByDesc('v.version_no')
            ->get([
                'v.id', 'v.version_no', 'v.type', 'v.stem', 'v.payload', 'v.max_mark', 'v.status', 'v.created_by',
                'v.submitted_at', 'v.decided_by', 'v.decided_at', 'v.decision_note', 'v.created_at', 'v.updated_at',
                'cu.name as author_name', 'su.name as submitted_by_name', 'du.name as decided_by_name',
            ])
            ->map(static function (object $v): array {
                $payload = json_decode((string) $v->payload, true, 32, JSON_THROW_ON_ERROR);

                return [
                    'id' => (int) $v->id,
                    'version_no' => (int) $v->version_no,
                    'type' => (string) $v->type,
                    'stem' => (string) $v->stem,
                    'payload' => is_array($payload) ? $payload : [],
                    'max_mark' => (string) $v->max_mark,
                    'status' => (string) $v->status,
                    'created_by' => (int) $v->created_by,
                    'author_name' => $v->author_name,
                    'created_at' => self::instant($v->created_at),
                    'submitted_at' => self::instant($v->submitted_at),
                    'submitted_by_name' => $v->submitted_by_name,
                    'decided_by' => $v->decided_by !== null ? (int) $v->decided_by : null,
                    'decided_by_name' => $v->decided_by_name,
                    'decided_at' => self::instant($v->decided_at),
                    'decision_note' => $v->decision_note,
                    'updated_at' => self::instant($v->updated_at),
                ];
            })
            ->all();

        return [
            'id' => (int) $q->id,
            'subject_id' => (int) $q->subject_id,
            'subject_name' => (string) $q->subject_name,
            'subject_name_en' => $q->subject_name_en,
            'status' => (string) $q->status,
            'created_by' => $q->created_by !== null ? (int) $q->created_by : null,
            'retired_at' => self::instant($q->retired_at),
            'retire_reason' => $q->retire_reason,
            'retired_by_name' => $q->retired_by_name,
            'created_at' => self::instant($q->created_at),
            'versions' => array_values($versions),
        ];
    }

    public function subjects(?array $subjectIds): array
    {
        if ($subjectIds === []) {
            return [];
        }

        return array_values(DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('status', 1)
            ->when($subjectIds !== null, static fn (Builder $q) => $q->whereIn('id', $subjectIds))
            ->orderBy('name')
            ->get(['id', 'name', 'name_en', 'code'])
            ->map(static fn (object $s): array => [
                'id' => (int) $s->id,
                'name' => (string) $s->name,
                'name_en' => $s->name_en !== null ? (string) $s->name_en : null,
                'code' => (string) $s->code,
            ])
            ->all());
    }

    /**
     * A TIMESTAMPTZ as ISO 8601 in UTC (e.g. `2026-10-27T13:16:44+00:00`): the stored instant, unchanged and parseable
     * by every browser. The page formats it in the display time zone (EXAMIS L-F7).
     */
    private static function instant(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse((string) $value)->utc()->toIso8601String();
    }
}
