<?php

namespace App\Infrastructure\Persistence\Teachers;

use App\Database\SchemaHelper;
use App\Domain\Teachers\Repositories\AcademicTitleRepositoryInterface;
use Illuminate\Support\Facades\DB;

final class EloquentAcademicTitleRepository implements AcademicTitleRepositoryInterface
{
    private const ACTIVE = 1;

    public function active(): array
    {
        return DB::table(SchemaHelper::qualified('teachers', 'academic_titles'))
            ->where('status', self::ACTIVE)
            ->orderBy('sort_order')->orderBy('id')
            ->get(['id', 'code', 'name', 'abbreviation'])
            ->map(static fn (object $r): array => [
                'id' => (int) $r->id,
                'code' => (string) $r->code,
                'name' => (string) $r->name,
                'abbreviation' => $r->abbreviation !== null ? (string) $r->abbreviation : null,
            ])->all();
    }

    public function activeExists(int $titleId): bool
    {
        return DB::table(SchemaHelper::qualified('teachers', 'academic_titles'))
            ->where('id', $titleId)->where('status', self::ACTIVE)->exists();
    }
}
