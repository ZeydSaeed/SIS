<?php

namespace App\Infrastructure\Persistence\Curriculum;

use App\Database\SchemaHelper;
use App\Domain\Curriculum\Data\PrerequisiteSnapshot;
use App\Domain\Curriculum\Repositories\PrerequisiteRepositoryInterface;
use App\Domain\Curriculum\ValueObjects\PrerequisiteStatus;
use Illuminate\Support\Facades\DB;

final class EloquentPrerequisiteRepository implements PrerequisiteRepositoryInterface
{
    public function subjectExists(int $subjectId): bool
    {
        return DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $subjectId)
            ->where('status', 1)
            ->exists();
    }

    public function addOrReactivate(int $subjectId, int $prerequisiteSubjectId, string $createdAt): int
    {
        $existing = DB::table(SchemaHelper::qualified('curriculum', 'prerequisites'))
            ->where('subject_id', $subjectId)
            ->where('prerequisite_subject_id', $prerequisiteSubjectId)
            ->first(['id', 'status']);

        if ($existing !== null) {
            if ((int) $existing->status !== PrerequisiteStatus::Active->value) {
                DB::table(SchemaHelper::qualified('curriculum', 'prerequisites'))
                    ->where('id', (int) $existing->id)
                    ->update(['status' => PrerequisiteStatus::Active->value]);
            }

            return (int) $existing->id;
        }

        return (int) DB::table(SchemaHelper::qualified('curriculum', 'prerequisites'))->insertGetId([
            'subject_id' => $subjectId,
            'prerequisite_subject_id' => $prerequisiteSubjectId,
            'status' => PrerequisiteStatus::Active->value,
            'created_at' => $createdAt,
        ]);
    }

    public function deactivate(int $prerequisiteId): bool
    {
        $updated = DB::table(SchemaHelper::qualified('curriculum', 'prerequisites'))
            ->where('id', $prerequisiteId)
            ->where('status', PrerequisiteStatus::Active->value)
            ->update(['status' => PrerequisiteStatus::Inactive->value]);

        return $updated > 0;
    }

    public function reactivate(int $prerequisiteId): bool
    {
        $updated = DB::table(SchemaHelper::qualified('curriculum', 'prerequisites'))
            ->where('id', $prerequisiteId)
            ->where('status', PrerequisiteStatus::Inactive->value)
            ->update(['status' => PrerequisiteStatus::Active->value]);

        return $updated > 0;
    }

    public function findActive(int $prerequisiteId): ?PrerequisiteSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('curriculum', 'prerequisites'))
            ->where('id', $prerequisiteId)
            ->where('status', PrerequisiteStatus::Active->value)
            ->first(['id', 'subject_id', 'prerequisite_subject_id', 'status', 'created_at']);

        return $row === null ? null : $this->map($row);
    }

    public function findInactive(int $prerequisiteId): ?PrerequisiteSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('curriculum', 'prerequisites'))
            ->where('id', $prerequisiteId)
            ->where('status', PrerequisiteStatus::Inactive->value)
            ->first(['id', 'subject_id', 'prerequisite_subject_id', 'status', 'created_at']);

        return $row === null ? null : $this->map($row);
    }

    public function listActiveForSubject(int $subjectId): array
    {
        $rows = DB::table(SchemaHelper::qualified('curriculum', 'prerequisites'))
            ->where('subject_id', $subjectId)
            ->where('status', PrerequisiteStatus::Active->value)
            ->orderBy('id')
            ->get(['id', 'subject_id', 'prerequisite_subject_id', 'status', 'created_at']);

        return $rows->map(fn ($row): PrerequisiteSnapshot => $this->map($row))->all();
    }

    public function listForSubject(int $subjectId): array
    {
        $rows = DB::table(SchemaHelper::qualified('curriculum', 'prerequisites'))
            ->where('subject_id', $subjectId)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [PrerequisiteStatus::Active->value])
            ->orderBy('id')
            ->get(['id', 'subject_id', 'prerequisite_subject_id', 'status', 'created_at']);

        return $rows->map(fn ($row): PrerequisiteSnapshot => $this->map($row))->all();
    }

    public function activePrerequisiteSubjectIds(int $subjectId): array
    {
        return DB::table(SchemaHelper::qualified('curriculum', 'prerequisites'))
            ->where('subject_id', $subjectId)
            ->where('status', PrerequisiteStatus::Active->value)
            ->orderBy('id')
            ->pluck('prerequisite_subject_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function activePrerequisiteSubjectIdsForSubjects(array $subjectIds): array
    {
        $ids = array_values(array_unique(array_filter(
            $subjectIds,
            static fn (int $id): bool => $id > 0,
        )));
        if ($ids === []) {
            return [];
        }

        $rows = DB::table(SchemaHelper::qualified('curriculum', 'prerequisites'))
            ->whereIn('subject_id', $ids)
            ->where('status', PrerequisiteStatus::Active->value)
            ->orderBy('id')
            ->get(['subject_id', 'prerequisite_subject_id']);

        $map = [];
        foreach ($ids as $subjectId) {
            $map[$subjectId] = [];
        }
        foreach ($rows as $row) {
            $subjectId = (int) $row->subject_id;
            $map[$subjectId][] = (int) $row->prerequisite_subject_id;
        }

        return $map;
    }

    private function map(object $row): PrerequisiteSnapshot
    {
        return new PrerequisiteSnapshot(
            id: (int) $row->id,
            subjectId: (int) $row->subject_id,
            prerequisiteSubjectId: (int) $row->prerequisite_subject_id,
            status: (int) $row->status,
            createdAt: (string) $row->created_at,
        );
    }
}
