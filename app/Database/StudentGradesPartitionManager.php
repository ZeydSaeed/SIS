<?php

namespace App\Database;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Explicit LIST partitions for exams.student_grades (no DEFAULT partition).
 * Ops: call ensurePartitionForAcademicYear after creating a new academic year.
 */
final class StudentGradesPartitionManager
{
    public static function partitionTableName(int $academicYearId): string
    {
        if ($academicYearId <= 0) {
            throw new InvalidArgumentException('academic_year_id must be positive.');
        }

        return 'student_grades_ay_'.$academicYearId;
    }

    public static function ensurePartitionForAcademicYear(int $academicYearId): void
    {
        if (! SchemaHelper::isPostgreSql()) {
            return;
        }

        if ($academicYearId <= 0) {
            throw new InvalidArgumentException('academic_year_id must be positive.');
        }

        $exists = DB::selectOne('SELECT 1 AS ok FROM academic.academic_years WHERE id = ?', [$academicYearId]);
        if ($exists === null) {
            throw new InvalidArgumentException("academic_year_id [{$academicYearId}] does not exist.");
        }

        $partition = self::partitionTableName($academicYearId);

        DB::statement("
            CREATE TABLE IF NOT EXISTS exams.{$partition}
            PARTITION OF exams.student_grades
            FOR VALUES IN ({$academicYearId})
        ");
    }

    public static function partitionExists(int $academicYearId): bool
    {
        if (! SchemaHelper::isPostgreSql()) {
            return true;
        }

        $partition = self::partitionTableName($academicYearId);

        $row = DB::selectOne("
            SELECT 1 AS ok
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            WHERE n.nspname = 'exams' AND c.relname = ?
        ", [$partition]);

        return $row !== null;
    }

    /**
     * @return list<int>
     */
    public static function ensurePartitionsForExistingYears(): array
    {
        if (! SchemaHelper::isPostgreSql()) {
            return [];
        }

        $ids = collect(DB::select('SELECT id FROM academic.academic_years ORDER BY id'))
            ->map(fn ($row) => (int) $row->id)
            ->all();

        foreach ($ids as $id) {
            self::ensurePartitionForAcademicYear($id);
        }

        return $ids;
    }
}
