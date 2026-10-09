<?php

namespace App\Infrastructure\Persistence\Organization;

use App\Database\SchemaHelper;
use App\Domain\Organization\Repositories\OrganizationAppearanceRepositoryInterface;
use App\Domain\Shared\ValueObjects\DisplayAppearance;
use Illuminate\Support\Facades\DB;

final class EloquentOrganizationAppearanceRepository implements OrganizationAppearanceRepositoryInterface
{
    private const TABLES = [
        'branch' => 'branches',
        'department' => 'departments',
        'room' => 'rooms',
        'room_type' => 'room_types',
    ];

    public function belongsToSchool(string $target, int $schoolId, int $id): bool
    {
        return match ($target) {
            'branch', 'department' => DB::table($this->table($target))->where('id', $id)->where('school_id', $schoolId)->exists(),
            'room' => DB::table($this->table('room').' as r')
                ->join($this->table('branch').' as b', 'b.id', '=', 'r.branch_id')
                ->where('r.id', $id)->where('b.school_id', $schoolId)->exists(),
            // System room types (school_id NULL) are shared — only the school's own types are re-styled here.
            'room_type' => DB::table($this->table('room_type'))->where('id', $id)->where('school_id', $schoolId)->exists(),
            default => false,
        };
    }

    public function setAppearance(string $target, int $id, DisplayAppearance $appearance, string $at): void
    {
        DB::table($this->table($target))->where('id', $id)->update($appearance->toArray() + ['updated_at' => $at]);
    }

    private function table(string $target): string
    {
        return SchemaHelper::qualified('organization', self::TABLES[$target]);
    }
}
