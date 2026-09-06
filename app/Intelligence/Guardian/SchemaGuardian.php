<?php

namespace App\Intelligence\Guardian;

use App\Database\SchemaHelper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SchemaGuardian
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function validate(): Collection
    {
        $findings = collect();

        $findings = $findings->merge($this->checkAcademicYearColumns());
        $findings = $findings->merge($this->checkForeignKeys());
        $findings = $findings->merge($this->checkDenormalizedWideTables());

        return $findings;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function checkAcademicYearColumns(): Collection
    {
        $findings = collect();

        foreach (config('intelligence.schema_guardian.academic_year_column_tables', []) as $qualified) {
            if (! $this->tableExists($qualified)) {
                continue;
            }

            [$schema, $table] = $this->splitQualified($qualified);

            if (! $this->columnExists($schema, $table, 'academic_year_id')) {
                $findings->push([
                    'code' => 'MISSING_ACADEMIC_YEAR',
                    'severity' => 'critical',
                    'table' => $qualified,
                    'message' => 'Academic transactional table missing academic_year_id column',
                    'normalization_violation' => false,
                    'missing_foreign_key' => false,
                ]);
            }
        }

        return $findings;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function checkForeignKeys(): Collection
    {
        if (! SchemaHelper::isPostgreSql()) {
            return collect();
        }

        $findings = collect();

        $orphanCandidates = DB::select("
            SELECT
                tc.table_schema,
                tc.table_name,
                kcu.column_name
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
                AND tc.table_schema = kcu.table_schema
            LEFT JOIN information_schema.referential_constraints rc
                ON rc.constraint_name = tc.constraint_name
                AND rc.constraint_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema NOT IN ('pg_catalog', 'information_schema', 'intelligence')
        ");

        $fkColumns = collect($orphanCandidates)->map(fn ($row) => "{$row->table_schema}.{$row->table_name}.{$row->column_name}");

        $expectedRelationships = [
            ['table' => 'enrollment.enrollments', 'column' => 'student_id', 'references' => 'students.students.id'],
        ];

        foreach ($expectedRelationships as $relationship) {
            [$schema, $table] = $this->splitQualified($relationship['table']);
            if (! $this->tableExists($relationship['table'])) {
                continue;
            }

            $hasFk = $fkColumns->contains(fn ($item) => str_starts_with($item, "{$schema}.{$table}.{$relationship['column']}"));

            if (! $hasFk) {
                $findings->push([
                    'code' => 'MISSING_FOREIGN_KEY',
                    'severity' => 'warning',
                    'table' => $relationship['table'],
                    'column' => $relationship['column'],
                    'message' => "Missing FK: {$relationship['table']}.{$relationship['column']} → {$relationship['references']}",
                    'normalization_violation' => false,
                    'missing_foreign_key' => true,
                ]);
            }
        }

        return $findings;
    }

    /**
     * Detect obvious 1NF violations — single table storing grades/attendance/enrollment together.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function checkDenormalizedWideTables(): Collection
    {
        $findings = collect();
        $forbiddenCombo = ['student_id', 'school_id', 'grade_value', 'attendance_date'];

        if (! SchemaHelper::isPostgreSql()) {
            return $findings;
        }

        $tables = DB::select("
            SELECT table_schema, table_name
            FROM information_schema.tables
            WHERE table_schema NOT IN ('pg_catalog', 'information_schema', 'intelligence')
              AND table_type = 'BASE TABLE'
        ");

        foreach ($tables as $table) {
            $columns = DB::select('
                SELECT column_name
                FROM information_schema.columns
                WHERE table_schema = ? AND table_name = ?
            ', [$table->table_schema, $table->table_name]);

            $columnNames = collect($columns)->pluck('column_name')->all();
            $matches = collect($forbiddenCombo)->filter(fn ($col) => in_array($col, $columnNames, true));

            if ($matches->count() >= 3) {
                $findings->push([
                    'code' => 'NORMALIZATION_VIOLATION',
                    'severity' => 'critical',
                    'table' => "{$table->table_schema}.{$table->table_name}",
                    'message' => 'Wide table mixes student identity, enrollment, grades, and attendance — violates normalization',
                    'normalization_violation' => true,
                    'missing_foreign_key' => false,
                ]);
            }
        }

        return $findings;
    }

    private function tableExists(string $qualified): bool
    {
        [$schema, $table] = $this->splitQualified($qualified);

        if (SchemaHelper::isPostgreSql()) {
            return Schema::hasTable("{$schema}.{$table}");
        }

        return Schema::hasTable(SchemaHelper::qualified($schema, $table));
    }

    private function columnExists(string $schema, string $table, string $column): bool
    {
        if (SchemaHelper::isPostgreSql()) {
            $result = DB::selectOne('
                SELECT 1
                FROM information_schema.columns
                WHERE table_schema = ? AND table_name = ? AND column_name = ?
            ', [$schema, $table, $column]);

            return $result !== null;
        }

        return Schema::hasColumn(SchemaHelper::qualified($schema, $table), $column);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitQualified(string $qualified): array
    {
        [$schema, $table] = explode('.', $qualified, 2);

        return [$schema, $table];
    }
}
