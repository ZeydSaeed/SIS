<?php

namespace App\Infrastructure\Persistence\Organization;

use App\Application\Organization\Contracts\SchoolRegistryReadRepositoryInterface;
use App\Application\Organization\DTOs\DirectorateRegistryItemDTO;
use App\Application\Organization\DTOs\SchoolRegistryItemDTO;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

final class EloquentSchoolRegistryReadRepository implements SchoolRegistryReadRepositoryInterface
{
    public function listSchools(array $schoolIds): array
    {
        if ($schoolIds === []) {
            return [];
        }

        return DB::table(SchemaHelper::qualified('organization', 'schools').' as s')
            ->leftJoin(
                SchemaHelper::qualified('organization', 'directorates').' as d',
                'd.id',
                '=',
                's.directorate_id',
            )
            ->whereIn('s.id', $schoolIds)
            ->orderBy('s.name')
            ->orderBy('s.id')
            ->get([
                's.id',
                's.directorate_id',
                'd.name as directorate_name',
                's.code',
                's.name',
                's.address',
                's.phone',
                's.email',
                's.status',
            ])
            ->map(static fn ($row): SchoolRegistryItemDTO => new SchoolRegistryItemDTO(
                id: (int) $row->id,
                directorateId: (int) $row->directorate_id,
                directorateName: $row->directorate_name !== null ? (string) $row->directorate_name : null,
                code: (string) $row->code,
                name: (string) $row->name,
                address: $row->address !== null ? (string) $row->address : null,
                phone: $row->phone !== null ? (string) $row->phone : null,
                email: $row->email !== null ? (string) $row->email : null,
                status: (int) $row->status,
            ))
            ->all();
    }

    public function listDirectorates(array $schoolIds): array
    {
        /** @var array<int, list<int>> $schoolsByDirectorate */
        $schoolsByDirectorate = [];
        if ($schoolIds !== []) {
            $rows = DB::table(SchemaHelper::qualified('organization', 'schools'))
                ->whereIn('id', $schoolIds)
                ->orderBy('id')
                ->get(['id', 'directorate_id']);
            foreach ($rows as $row) {
                $schoolsByDirectorate[(int) $row->directorate_id][] = (int) $row->id;
            }
        }

        return DB::table(SchemaHelper::qualified('organization', 'directorates'))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'region', 'status'])
            ->map(static fn ($row): DirectorateRegistryItemDTO => new DirectorateRegistryItemDTO(
                id: (int) $row->id,
                name: (string) $row->name,
                region: $row->region !== null ? (string) $row->region : null,
                status: (int) $row->status,
                schoolIds: $schoolsByDirectorate[(int) $row->id] ?? [],
            ))
            ->all();
    }
}
