<?php

namespace App\Database;

use Illuminate\Support\Facades\DB;

class SchemaHelper
{
    /**
     * @return list<string>
     */
    public static function schemas(): array
    {
        return [
            'organization',
            'academic',
            'vocational',
            'students',
            'guardians',
            'enrollment',
            'teachers',
            'curriculum',
            'timetable',
            'attendance',
            'exams',
            'results',
            'promotion',
            'transfers',
            'graduation',
            'certificates',
            'documents',
            'finance',
            'communication',
            'workflow',
            'security',
            'audit',
            'reports',
            'intelligence',
        ];
    }

    public static function isPostgreSql(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    public static function qualified(string $schema, string $table): string
    {
        return self::isPostgreSql()
            ? "{$schema}.{$table}"
            : "{$schema}_{$table}";
    }

    public static function createSchemas(): void
    {
        if (! self::isPostgreSql()) {
            return;
        }

        foreach (self::schemas() as $schema) {
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
        }
    }

    public static function dropSchemas(): void
    {
        if (! self::isPostgreSql()) {
            return;
        }

        foreach (array_reverse(self::schemas()) as $schema) {
            DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
        }
    }
}
