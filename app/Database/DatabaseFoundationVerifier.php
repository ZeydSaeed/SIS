<?php

namespace App\Database;

use Database\Seeders\Support\FoundationReference;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DatabaseFoundationVerifier
{
    /** @var list<string> */
    private const REQUIRED_TABLES = [
        'organization.ministries',
        'organization.directorates',
        'organization.schools',
        'academic.academic_years',
        'academic.grade_levels',
        'academic.terms',
        'students.students',
        'enrollment.enrollments',
        'admission.application_periods',
        'admission.applications',
        'intelligence.monitoring_snapshots',
    ];

    public function __construct(
        private readonly Migrator $migrator,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     driver: string,
     *     connection: string,
     *     checks: list<array{name: string, status: string, detail: string}>,
     *     summary: array<string, int|string|bool|null>
     * }
     */
    public function verify(bool $requireFoundationSeed = true): array
    {
        $driver = DB::connection()->getDriverName();
        $checks = [];

        $checks[] = $this->checkDriver($driver);
        $checks[] = $this->checkPendingMigrations();
        $checks = array_merge($checks, $this->checkRequiredTables($driver));

        if ($driver === 'pgsql') {
            $checks = array_merge($checks, $this->checkPostgreSqlSchemas());
            $checks[] = $this->checkMinimumTableCount();
        }

        if ($requireFoundationSeed) {
            $checks[] = $this->checkFoundationSeed();
        }

        $failed = array_filter($checks, fn (array $check): bool => $check['status'] !== 'pass');

        return [
            'ok' => $failed === [],
            'driver' => $driver,
            'connection' => (string) config('database.default'),
            'checks' => $checks,
            'summary' => [
                'passed' => count($checks) - count($failed),
                'failed' => count($failed),
                'total_checks' => count($checks),
                'table_count' => $driver === 'pgsql' ? $this->countPostgreSqlTables() : null,
                'foundation_seed_present' => $this->foundationSeedPresent(),
            ],
        ];
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function checkDriver(string $driver): array
    {
        if ($driver === 'pgsql') {
            return [
                'name' => 'database_driver',
                'status' => 'pass',
                'detail' => 'PostgreSQL connected',
            ];
        }

        return [
            'name' => 'database_driver',
            'status' => 'warn',
            'detail' => "Connected via {$driver}; PostgreSQL recommended for SIS development",
        ];
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function checkPendingMigrations(): array
    {
        $this->migrator->setConnection(DB::connection()->getName());

        $files = $this->migrator->getMigrationFiles(database_path('migrations'));
        $ran = $this->migrator->getRepository()->getRan();
        $pending = array_diff(array_keys($files), $ran);

        if ($pending === []) {
            return [
                'name' => 'migrations',
                'status' => 'pass',
                'detail' => 'All migrations applied',
            ];
        }

        return [
            'name' => 'migrations',
            'status' => 'fail',
            'detail' => count($pending).' pending migration(s): '.implode(', ', array_slice($pending, 0, 5)),
        ];
    }

    /**
     * @return list<array{name: string, status: string, detail: string}>
     */
    private function checkRequiredTables(string $driver): array
    {
        $checks = [];

        foreach (self::REQUIRED_TABLES as $qualified) {
            [$schema, $table] = explode('.', $qualified, 2);
            $physical = SchemaHelper::qualified($schema, $table);
            $exists = Schema::hasTable($physical);

            $checks[] = [
                'name' => "table:{$qualified}",
                'status' => $exists ? 'pass' : 'fail',
                'detail' => $exists
                    ? "Table {$physical} exists"
                    : "Missing table {$physical} (driver={$driver})",
            ];
        }

        return $checks;
    }

    /**
     * @return list<array{name: string, status: string, detail: string}>
     */
    private function checkPostgreSqlSchemas(): array
    {
        $checks = [];
        $existing = DB::table('information_schema.schemata')
            ->whereIn('schema_name', SchemaHelper::schemas())
            ->pluck('schema_name')
            ->all();

        foreach (SchemaHelper::schemas() as $schema) {
            $present = in_array($schema, $existing, true);
            $checks[] = [
                'name' => "schema:{$schema}",
                'status' => $present ? 'pass' : 'fail',
                'detail' => $present ? "Schema {$schema} exists" : "Missing schema {$schema}",
            ];
        }

        return $checks;
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function checkMinimumTableCount(): array
    {
        $count = $this->countPostgreSqlTables();
        $minimum = 50;

        return [
            'name' => 'table_count',
            'status' => $count >= $minimum ? 'pass' : 'fail',
            'detail' => "{$count} business tables found (minimum {$minimum})",
        ];
    }

    /**
     * @return array{name: string, status: string, detail: string}
     */
    private function checkFoundationSeed(): array
    {
        if ($this->foundationSeedPresent()) {
            return [
                'name' => 'foundation_seed',
                'status' => 'pass',
                'detail' => 'Foundation school and academic year present',
            ];
        }

        return [
            'name' => 'foundation_seed',
            'status' => 'fail',
            'detail' => 'Run: php artisan db:seed --class=SisFoundationSeeder',
        ];
    }

    private function foundationSeedPresent(): bool
    {
        $schoolTable = SchemaHelper::qualified('organization', 'schools');
        $yearTable = SchemaHelper::qualified('academic', 'academic_years');

        if (! Schema::hasTable($schoolTable) || ! Schema::hasTable($yearTable)) {
            return false;
        }

        $schoolExists = DB::table($schoolTable)
            ->where('code', FoundationReference::SCHOOL_CODE)
            ->exists();

        $yearExists = DB::table($yearTable)
            ->where('code', FoundationReference::ACADEMIC_YEAR_CODE)
            ->where('is_current', true)
            ->exists();

        return $schoolExists && $yearExists;
    }

    private function countPostgreSqlTables(): int
    {
        if (! SchemaHelper::isPostgreSql()) {
            return 0;
        }

        return (int) DB::table('information_schema.tables')
            ->whereIn('table_schema', SchemaHelper::schemas())
            ->count();
    }
}
