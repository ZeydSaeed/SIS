<?php

namespace App\Infrastructure\Persistence\Exams;

use App\Application\Exams\Contracts\ExamDisplayNamesReadRepositoryInterface;
use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

final class EloquentExamDisplayNamesReadRepository implements ExamDisplayNamesReadRepositoryInterface
{
    public function names(array $termIds, array $academicYearIds, array $subjectIds): array
    {
        return [
            'terms' => $this->lookup(SchemaHelper::qualified('academic', 'terms'), $termIds),
            'academic_years' => $this->lookup(SchemaHelper::qualified('academic', 'academic_years'), $academicYearIds),
            'subjects' => $this->lookup(SchemaHelper::qualified('curriculum', 'subjects'), $subjectIds),
        ];
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function lookup(string $table, array $ids): array
    {
        $ids = array_values(array_unique($ids));
        if ($ids === []) {
            return [];
        }

        return DB::table($table)->whereIn('id', $ids)->pluck('name', 'id')
            ->mapWithKeys(static fn ($name, $id): array => [(int) $id => (string) $name])
            ->all();
    }
}
