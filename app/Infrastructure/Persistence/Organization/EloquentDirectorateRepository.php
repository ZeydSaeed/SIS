<?php

namespace App\Infrastructure\Persistence\Organization;

use App\Database\SchemaHelper;
use App\Domain\Organization\Data\DirectorateSnapshot;
use App\Domain\Organization\Repositories\DirectorateRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentDirectorateRepository implements DirectorateRepositoryInterface
{
    private const UPDATABLE = ['name', 'region'];

    public function find(int $directorateId): ?DirectorateSnapshot
    {
        $row = DB::table(SchemaHelper::qualified('organization', 'directorates'))
            ->where('id', $directorateId)
            ->first(['id', 'ministry_id', 'code', 'name', 'region', 'status']);

        if ($row === null) {
            return null;
        }

        return new DirectorateSnapshot(
            id: (int) $row->id,
            ministryId: (int) $row->ministry_id,
            code: (string) $row->code,
            name: (string) $row->name,
            region: $row->region !== null ? (string) $row->region : null,
            status: (int) $row->status,
        );
    }

    public function defaultMinistryId(): ?int
    {
        $id = DB::table(SchemaHelper::qualified('organization', 'ministries'))
            ->where('status', 1)
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    public function nextCode(): string
    {
        return OrganizationCodeSequence::next(SchemaHelper::qualified('organization', 'directorates'), 'DIR');
    }

    public function create(int $ministryId, string $code, string $name, ?string $region, string $createdAt): int
    {
        return (int) DB::table(SchemaHelper::qualified('organization', 'directorates'))->insertGetId([
            'ministry_id' => $ministryId,
            'code' => $code,
            'name' => $name,
            'region' => $region,
            'status' => 1,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    public function update(int $directorateId, array $fields, string $updatedAt): bool
    {
        $payload = ['updated_at' => $updatedAt];
        foreach (self::UPDATABLE as $key) {
            if (array_key_exists($key, $fields)) {
                $payload[$key] = $fields[$key];
            }
        }

        return DB::table(SchemaHelper::qualified('organization', 'directorates'))
            ->where('id', $directorateId)
            ->update($payload) > 0;
    }

    public function setStatus(int $directorateId, int $status, string $updatedAt): bool
    {
        return DB::table(SchemaHelper::qualified('organization', 'directorates'))
            ->where('id', $directorateId)
            ->update(['status' => $status, 'updated_at' => $updatedAt]) > 0;
    }

    public function activeSchoolCount(int $directorateId): int
    {
        return DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('directorate_id', $directorateId)
            ->where('status', 1)
            ->count();
    }

    public function schoolIdsIn(int $directorateId, array $schoolIds): array
    {
        if ($schoolIds === []) {
            return [];
        }

        return DB::table(SchemaHelper::qualified('organization', 'schools'))
            ->where('directorate_id', $directorateId)
            ->whereIn('id', $schoolIds)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
