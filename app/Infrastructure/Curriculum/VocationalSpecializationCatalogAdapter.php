<?php

namespace App\Infrastructure\Curriculum;

use App\Database\SchemaHelper;
use App\Domain\Curriculum\Contracts\SpecializationCatalogPort;
use App\Domain\Curriculum\Data\SpecializationSubjectTemplate;
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

    public function listActiveSubjectTemplates(int $schoolId, int $specializationId): array
    {
        if (! $this->activeInSchool($schoolId, $specializationId)) {
            return [];
        }

        $links = SchemaHelper::qualified('vocational', 'specialization_subjects');
        $subjects = SchemaHelper::qualified('curriculum', 'subjects');

        $rows = DB::table($links.' as ss')
            ->join($subjects.' as s', 's.id', '=', 'ss.subject_id')
            ->where('ss.specialization_id', $specializationId)
            ->where('ss.status', 1)
            ->where('s.status', 1)
            ->orderBy('ss.id')
            ->get(['ss.subject_id', 'ss.credit_hours', 'ss.is_required']);

        return $rows->map(static fn (object $row): SpecializationSubjectTemplate => new SpecializationSubjectTemplate(
            subjectId: (int) $row->subject_id,
            creditHours: $row->credit_hours !== null ? (int) $row->credit_hours : null,
            isRequired: (bool) $row->is_required,
        ))->all();
    }
}
