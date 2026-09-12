<?php

namespace App\Infrastructure\Persistence\Vocational;

use App\Database\SchemaHelper;
use App\Domain\Vocational\Data\SpecializationRead;
use App\Domain\Vocational\Exceptions\VocationalNotFoundException;
use App\Domain\Vocational\Exceptions\VocationalValidationException;
use App\Domain\Vocational\Repositories\VocationalCatalogRepositoryInterface;
use App\Domain\Vocational\ValueObjects\VocationalCatalogStatus;
use Illuminate\Support\Facades\DB;

final class EloquentVocationalCatalogRepository implements VocationalCatalogRepositoryInterface
{
    public function createSpecialization(
        int $schoolId,
        string $code,
        string $name,
        ?string $description,
        string $at,
    ): int {
        $this->requireCode($code);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        return (int) DB::table(SchemaHelper::qualified('vocational', 'specializations'))->insertGetId([
            'school_id' => $schoolId,
            'code' => $code,
            'name' => $name,
            'description' => $description,
            'status' => VocationalCatalogStatus::Active->value,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function updateSpecialization(
        int $schoolId,
        int $specializationId,
        string $code,
        string $name,
        ?string $description,
        string $at,
    ): void {
        $this->requireCode($code);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $updated = DB::table(SchemaHelper::qualified('vocational', 'specializations'))
            ->where('id', $specializationId)
            ->where('school_id', $schoolId)
            ->where('status', VocationalCatalogStatus::Active->value)
            ->update([
                'code' => $code,
                'name' => $name,
                'description' => $description,
                'updated_at' => $at,
            ]);

        if ($updated === 0) {
            throw VocationalNotFoundException::specialization($specializationId);
        }
    }

    public function deactivateSpecialization(int $schoolId, int $specializationId, string $at): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $updated = DB::table(SchemaHelper::qualified('vocational', 'specializations'))
            ->where('id', $specializationId)
            ->where('school_id', $schoolId)
            ->where('status', VocationalCatalogStatus::Active->value)
            ->update([
                'status' => VocationalCatalogStatus::Inactive->value,
                'updated_at' => $at,
            ]);

        if ($updated === 0) {
            throw VocationalNotFoundException::specialization($specializationId);
        }
    }

    public function createTrack(
        int $schoolId,
        int $specializationId,
        string $code,
        string $name,
        string $at,
    ): int {
        $this->requireCode($code);
        $this->requireActiveSpecialization($schoolId, $specializationId);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        return (int) DB::table(SchemaHelper::qualified('vocational', 'tracks'))->insertGetId([
            'specialization_id' => $specializationId,
            'code' => $code,
            'name' => $name,
            'status' => VocationalCatalogStatus::Active->value,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    public function updateTrack(
        int $schoolId,
        int $trackId,
        string $code,
        string $name,
        string $at,
    ): void {
        $this->requireCode($code);
        $this->requireTrackInSchool($schoolId, $trackId, activeOnly: true);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $updated = DB::table(SchemaHelper::qualified('vocational', 'tracks'))
            ->where('id', $trackId)
            ->where('status', VocationalCatalogStatus::Active->value)
            ->update([
                'code' => $code,
                'name' => $name,
                'updated_at' => $at,
            ]);

        if ($updated === 0) {
            throw VocationalNotFoundException::track($trackId);
        }
    }

    public function deactivateTrack(int $schoolId, int $trackId, string $at): void
    {
        $this->requireTrackInSchool($schoolId, $trackId, activeOnly: true);
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $updated = DB::table(SchemaHelper::qualified('vocational', 'tracks'))
            ->where('id', $trackId)
            ->where('status', VocationalCatalogStatus::Active->value)
            ->update([
                'status' => VocationalCatalogStatus::Inactive->value,
                'updated_at' => $at,
            ]);

        if ($updated === 0) {
            throw VocationalNotFoundException::track($trackId);
        }
    }

    public function linkSpecializationSubject(
        int $schoolId,
        int $specializationId,
        int $subjectId,
        bool $isRequired,
        ?int $creditHours,
    ): int {
        $this->requireActiveSpecialization($schoolId, $specializationId);
        $subjectOk = DB::table(SchemaHelper::qualified('curriculum', 'subjects'))
            ->where('id', $subjectId)
            ->exists();
        if (! $subjectOk) {
            throw VocationalValidationException::withReason('vocational.subject_not_found');
        }

        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);
        $table = SchemaHelper::qualified('vocational', 'specialization_subjects');
        $existing = DB::table($table)
            ->where('specialization_id', $specializationId)
            ->where('subject_id', $subjectId)
            ->first(['id', 'status']);

        if ($existing !== null) {
            if ((int) $existing->status === VocationalCatalogStatus::Active->value) {
                throw VocationalValidationException::withReason('vocational.subject_already_linked');
            }
            DB::table($table)->where('id', $existing->id)->update([
                'is_required' => $isRequired,
                'credit_hours' => $creditHours,
                'status' => VocationalCatalogStatus::Active->value,
            ]);

            return (int) $existing->id;
        }

        return (int) DB::table($table)->insertGetId([
            'specialization_id' => $specializationId,
            'subject_id' => $subjectId,
            'is_required' => $isRequired,
            'credit_hours' => $creditHours,
            'status' => VocationalCatalogStatus::Active->value,
        ]);
    }

    public function deactivateSpecializationSubject(int $schoolId, int $linkId): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $row = DB::table(SchemaHelper::qualified('vocational', 'specialization_subjects').' as ss')
            ->join(SchemaHelper::qualified('vocational', 'specializations').' as sp', 'sp.id', '=', 'ss.specialization_id')
            ->where('ss.id', $linkId)
            ->where('sp.school_id', $schoolId)
            ->where('ss.status', VocationalCatalogStatus::Active->value)
            ->first(['ss.id']);

        if ($row === null) {
            throw VocationalNotFoundException::subjectLink($linkId);
        }

        DB::table(SchemaHelper::qualified('vocational', 'specialization_subjects'))
            ->where('id', $linkId)
            ->update(['status' => VocationalCatalogStatus::Inactive->value]);
    }

    public function listSpecializations(
        int $schoolId,
        ?int $status,
        int $page,
        int $perPage,
    ): array {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $query = DB::table(SchemaHelper::qualified('vocational', 'specializations'))
            ->where('school_id', $schoolId);
        if ($status !== null) {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();
        $rows = $query
            ->orderBy('code')
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get(['id', 'school_id', 'code', 'name', 'description', 'status']);

        $items = [];
        foreach ($rows as $row) {
            $items[] = new SpecializationRead(
                id: (int) $row->id,
                schoolId: (int) $row->school_id,
                code: (string) $row->code,
                name: (string) $row->name,
                description: $row->description !== null ? (string) $row->description : null,
                status: (int) $row->status,
            );
        }

        return ['items' => $items, 'total' => $total];
    }

    public function findSpecialization(int $schoolId, int $specializationId, bool $withChildren = true): ?SpecializationRead
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, true)", [(string) $schoolId]);

        $row = DB::table(SchemaHelper::qualified('vocational', 'specializations'))
            ->where('school_id', $schoolId)
            ->where('id', $specializationId)
            ->first(['id', 'school_id', 'code', 'name', 'description', 'status']);

        if ($row === null) {
            return null;
        }

        $tracks = [];
        $subjectLinks = [];
        if ($withChildren) {
            $trackRows = DB::table(SchemaHelper::qualified('vocational', 'tracks'))
                ->where('specialization_id', $specializationId)
                ->orderBy('code')
                ->orderBy('id')
                ->get(['id', 'code', 'name', 'status']);
            foreach ($trackRows as $t) {
                $tracks[] = [
                    'id' => (int) $t->id,
                    'code' => (string) $t->code,
                    'name' => (string) $t->name,
                    'status' => (int) $t->status,
                ];
            }

            $linkRows = DB::table(SchemaHelper::qualified('vocational', 'specialization_subjects'))
                ->where('specialization_id', $specializationId)
                ->orderBy('id')
                ->get(['id', 'subject_id', 'is_required', 'credit_hours', 'status']);
            foreach ($linkRows as $l) {
                $subjectLinks[] = [
                    'id' => (int) $l->id,
                    'subject_id' => (int) $l->subject_id,
                    'is_required' => (bool) $l->is_required,
                    'credit_hours' => $l->credit_hours !== null ? (int) $l->credit_hours : null,
                    'status' => (int) $l->status,
                ];
            }
        }

        return new SpecializationRead(
            id: (int) $row->id,
            schoolId: (int) $row->school_id,
            code: (string) $row->code,
            name: (string) $row->name,
            description: $row->description !== null ? (string) $row->description : null,
            status: (int) $row->status,
            tracks: $tracks,
            subjectLinks: $subjectLinks,
        );
    }

    private function requireCode(string $code): void
    {
        if (trim($code) === '') {
            throw VocationalValidationException::withReason('vocational.code_required');
        }
    }

    private function requireActiveSpecialization(int $schoolId, int $specializationId): void
    {
        $ok = DB::table(SchemaHelper::qualified('vocational', 'specializations'))
            ->where('id', $specializationId)
            ->where('school_id', $schoolId)
            ->where('status', VocationalCatalogStatus::Active->value)
            ->exists();
        if (! $ok) {
            throw VocationalNotFoundException::specialization($specializationId);
        }
    }

    private function requireTrackInSchool(int $schoolId, int $trackId, bool $activeOnly): void
    {
        $q = DB::table(SchemaHelper::qualified('vocational', 'tracks').' as t')
            ->join(SchemaHelper::qualified('vocational', 'specializations').' as sp', 'sp.id', '=', 't.specialization_id')
            ->where('t.id', $trackId)
            ->where('sp.school_id', $schoolId);
        if ($activeOnly) {
            $q->where('t.status', VocationalCatalogStatus::Active->value);
        }
        if (! $q->exists()) {
            throw VocationalNotFoundException::track($trackId);
        }
    }
}
