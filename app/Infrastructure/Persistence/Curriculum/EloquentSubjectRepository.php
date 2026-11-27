<?php

namespace App\Infrastructure\Persistence\Curriculum;

use App\Database\SchemaHelper;
use App\Domain\Curriculum\Data\SubjectSnapshot;
use App\Domain\Curriculum\Repositories\SubjectRepositoryInterface;
use App\Domain\Curriculum\ValueObjects\SubjectStatus;
use Illuminate\Support\Facades\DB;

final class EloquentSubjectRepository implements SubjectRepositoryInterface
{
    private const SELECT_COLUMNS = [
        'id', 'code', 'name', 'name_en', 'subject_type', 'credit_hours',
        'max_grade', 'pass_grade', 'status', 'prerequisites_text', 'abbreviation', 'color_hue',
    ];

    /** The columns plus the subject's type set as «1,3» (primary-key lookup per row; no N+1). */
    private function columns(): array
    {
        return [
            ...self::SELECT_COLUMNS,
            // «3:2,4:» = عملي 2 h, نظري (no hours).
            DB::raw("(SELECT string_agg(st.subject_type::text || ':' || COALESCE(st.hours::text, ''), ',' ORDER BY st.subject_type) FROM curriculum.subject_types st WHERE st.subject_id = curriculum.subjects.id) AS subject_types"),
        ];
    }

    public function replaceTypes(int $subjectId, array $typeHours): void
    {
        $table = SchemaHelper::qualified('curriculum', 'subject_types');
        DB::table($table)->where('subject_id', $subjectId)->whereNotIn('subject_type', array_keys($typeHours))->delete();
        DB::table($table)->upsert(
            array_map(
                static fn (int $type, ?int $hours): array => ['subject_id' => $subjectId, 'subject_type' => $type, 'hours' => $hours],
                array_keys($typeHours),
                array_values($typeHours),
            ),
            ['subject_id', 'subject_type'],
            ['hours'],
        );
    }

    public function codeExists(string $code): bool
    {
        return DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('code', $code)
            ->exists();
    }

    public function nameExists(string $name, ?int $exceptSubjectId = null): bool
    {
        return DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->whereRaw('lower(btrim(name)) = lower(?)', [trim($name)])
            ->when($exceptSubjectId !== null, static fn ($q) => $q->where('id', '<>', $exceptSubjectId))
            ->exists();
    }

    public function create(
        string $code,
        string $name,
        ?string $nameEn,
        int $subjectType,
        ?int $creditHours,
        int $maxGrade,
        int $passGrade,
        string $createdAt,
        ?string $prerequisitesText = null,
    ): int {
        return (int) DB::table(SchemaHelper::qualified('curriculum', 'subjects'))->insertGetId([
            'code' => $code,
            'name' => $name,
            'name_en' => $nameEn,
            'subject_type' => $subjectType,
            'credit_hours' => $creditHours,
            'max_grade' => $maxGrade,
            'pass_grade' => $passGrade,
            'prerequisites_text' => $prerequisitesText,
            'status' => SubjectStatus::Active->value,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function findActive(int $subjectId): ?SubjectSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $subjectId)
            ->where('status', SubjectStatus::Active->value)
            ->first($this->columns());

        return $row === null ? null : $this->map($row);
    }

    public function findInactive(int $subjectId): ?SubjectSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $subjectId)
            ->where('status', SubjectStatus::Inactive->value)
            ->first($this->columns());

        return $row === null ? null : $this->map($row);
    }

    public function listActive(): array
    {
        $rows = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('status', SubjectStatus::Active->value)
            ->orderBy('code')
            ->get($this->columns());

        return $rows->map(fn ($row): SubjectSnapshot => $this->map($row))->all();
    }

    public function listAll(): array
    {
        $rows = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [SubjectStatus::Active->value])
            ->orderBy('code')
            ->get($this->columns());

        return $rows->map(fn ($row): SubjectSnapshot => $this->map($row))->all();
    }

    public function search(
        ?int $status,
        ?int $subjectType,
        string $q,
        int $page,
        int $perPage,
    ): array {
        $query = DB::table(SchemaHelper::qualified('curriculum', 'subjects'));

        if ($status !== null) {
            $query->where('status', $status);
        }
        if ($subjectType !== null) {
            // Any of its types (an «عملي + نظري» subject is listed under both).
            $query->whereExists(fn ($q) => $q->selectRaw('1')
                ->from(SchemaHelper::qualified('curriculum', 'subject_types').' as st')
                ->whereColumn('st.subject_id', 'curriculum.subjects.id')
                ->where('st.subject_type', $subjectType));
        }

        $needle = trim($q);
        if ($needle !== '') {
            $like = '%'.$needle.'%';
            $query->where(function ($inner) use ($like): void {
                $inner->where('name', 'ilike', $like)
                    ->orWhere('code', 'ilike', $like)
                    ->orWhere('name_en', 'ilike', $like);
            });
        }

        $total = (int) (clone $query)->count();
        $page = max(1, $page);
        $perPage = min(max(1, $perPage), 100);
        $rows = $query
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [SubjectStatus::Active->value])
            ->orderBy('code')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get($this->columns());

        return [
            'items' => $rows->map(fn ($row): SubjectSnapshot => $this->map($row))->all(),
            'total' => $total,
        ];
    }

    public function deactivate(int $subjectId): bool
    {
        $updated = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $subjectId)
            ->where('status', SubjectStatus::Active->value)
            ->update([
                'status' => SubjectStatus::Inactive->value,
                'updated_at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            ]);

        return $updated > 0;
    }

    public function reactivate(int $subjectId): bool
    {
        $updated = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $subjectId)
            ->where('status', SubjectStatus::Inactive->value)
            ->update([
                'status' => SubjectStatus::Active->value,
                'updated_at' => (new \DateTimeImmutable)->format(\DateTimeInterface::ATOM),
            ]);

        return $updated > 0;
    }

    public function updateActive(int $subjectId, array $fields, string $updatedAt): bool
    {
        $payload = ['updated_at' => $updatedAt];
        foreach ([
            'name',
            'name_en',
            'subject_type',
            'credit_hours',
            'max_grade',
            'pass_grade',
            'prerequisites_text',
            'abbreviation',
            'color_hue',
        ] as $key) {
            if (array_key_exists($key, $fields)) {
                $payload[$key] = $fields[$key];
            }
        }

        $updated = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $subjectId)
            ->where('status', SubjectStatus::Active->value)
            ->update($payload);

        return $updated > 0;
    }

    private function map(object $row): SubjectSnapshot
    {
        // «3:2,4:» → [3 => 2, 4 => null]; rows that predate the set keep their one type.
        $typeHours = [];
        $packed = property_exists($row, 'subject_types') ? (string) ($row->subject_types ?? '') : '';
        foreach ($packed === '' ? [] : explode(',', $packed) as $pair) {
            [$type, $hours] = array_pad(explode(':', $pair, 2), 2, '');
            $typeHours[(int) $type] = $hours === '' ? null : (int) $hours;
        }
        if ($typeHours === []) {
            $typeHours[(int) $row->subject_type] = null;
        }

        $prerequisitesText = property_exists($row, 'prerequisites_text') && $row->prerequisites_text !== null
            ? (string) $row->prerequisites_text
            : null;

        return new SubjectSnapshot(
            id: (int) $row->id,
            code: (string) $row->code,
            name: (string) $row->name,
            nameEn: $row->name_en !== null ? (string) $row->name_en : null,
            subjectType: (int) $row->subject_type,
            creditHours: $row->credit_hours !== null ? (int) $row->credit_hours : null,
            maxGrade: (int) $row->max_grade,
            passGrade: (int) $row->pass_grade,
            status: (int) $row->status,
            prerequisitesText: $prerequisitesText !== null && trim($prerequisitesText) !== ''
                ? trim($prerequisitesText)
                : null,
            abbreviation: property_exists($row, 'abbreviation') && $row->abbreviation !== null ? (string) $row->abbreviation : null,
            colorHue: property_exists($row, 'color_hue') && $row->color_hue !== null ? (int) $row->color_hue : null,
            subjectTypes: array_keys($typeHours),
            subjectTypeHours: $typeHours,
        );
    }
}
