<?php

namespace App\Infrastructure\Curriculum;

use App\Database\SchemaHelper;
use App\Domain\Curriculum\Contracts\SpecializationCatalogPort;
use Illuminate\Support\Facades\DB;

final class VocationalSpecializationCatalogAdapter implements SpecializationCatalogPort
{
    public function activeInSchool(int $schoolId, int $specializationId): bool
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        return DB::table(SchemaHelper::qualified('vocational', 'specializations'))
            ->where('id', $specializationId)
            ->where('school_id', $schoolId)
            ->where('status', 1)
            ->exists();
    }
}
