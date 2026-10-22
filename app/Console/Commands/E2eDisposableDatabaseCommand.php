<?php

namespace App\Console\Commands;

use App\Database\DisposableE2eDatabase;
use App\Database\ProtectedDatabaseGuard;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Creates and drops the disposable copies of the e2e dataset used by specs that must never write to `sis_e2e`
 * (docs/testing/E2E-RUNBOOK.md §4.1). Both actions run on the server's `postgres` maintenance database, never on the
 * target, and every rule is in {@see DisposableE2eDatabase}:
 *
 * - create: `sis_e2e_<suffix>` · on SIS_DESTRUCTIVE_ALLOWED_DATABASES · not protected · not production · absent ·
 *   `CREATE DATABASE … TEMPLATE sis_e2e` (no migration runs), then a marker with the new database's OID;
 * - drop: the same name rules plus evidence — the marker of this machine names the database, the template and the
 *   server, and its OID equals the live one (a database re-created under the same name has another OID). Without
 *   --execute it only reports what it would do.
 *
 * DB_CONNECTION=pgsql SIS_DESTRUCTIVE_ALLOWED_DATABASES=sis_e2e_exams php artisan sis:e2e-disposable create sis_e2e_exams
 * DB_CONNECTION=pgsql SIS_DESTRUCTIVE_ALLOWED_DATABASES=sis_e2e_exams php artisan sis:e2e-disposable drop sis_e2e_exams [--execute]
 */
class E2eDisposableDatabaseCommand extends Command
{
    protected $signature = 'sis:e2e-disposable
                            {action : create | drop}
                            {database : The disposable copy (sis_e2e_<suffix>)}
                            {--execute : drop only — actually drop (otherwise a dry run)}';

    protected $description = 'Create or drop a disposable copy of the e2e dataset (fail-closed, with creation evidence)';

    private const MAINTENANCE = 'e2e_maintenance';

    public function handle(ProtectedDatabaseGuard $guard): int
    {
        $database = $guard->normalizeName((string) $this->argument('database'));
        $action = (string) $this->argument('action');
        if (! in_array($action, ['create', 'drop'], true)) {
            $this->components->error('Action must be create or drop.');

            return self::FAILURE;
        }
        if (config('database.connections.'.config('database.default').'.driver') !== 'pgsql') {
            $this->components->error('Refused: disposable copies need PostgreSQL (DB_CONNECTION=pgsql).');

            return self::FAILURE;
        }

        $production = app()->environment('production');
        $protected = $guard->isProtected($database) ? [...$guard->protectedNames(), $database] : $guard->protectedNames();
        $allowed = $guard->destructiveAllowedNames();
        $nameRefusal = DisposableE2eDatabase::nameRefusal($database, $production, $protected, $allowed);
        if ($nameRefusal !== null) {
            // Refused on configuration alone — no connection has been opened.
            $this->components->error("Refused: {$nameRefusal}.");

            return self::FAILURE;
        }

        return $action === 'create'
            ? $this->create($database, $production, $protected, $allowed)
            : $this->drop($database, $production, $protected, $allowed);
    }

    /**
     * @param  list<string>  $protected
     * @param  list<string>  $allowed
     */
    private function create(string $database, bool $production, array $protected, array $allowed): int
    {
        $db = $this->maintenance();
        $refusal = DisposableE2eDatabase::createRefusal(
            $database, $production, $protected, $allowed,
            exists: $this->live($db, $database) !== null,
            templateExists: $this->live($db, DisposableE2eDatabase::TEMPLATE) !== null,
        );
        if ($refusal !== null) {
            $this->components->error("Refused: {$refusal}.");

            return self::FAILURE;
        }

        // The name matched ^sis_e2e_[a-z0-9_]+$ — safe as a quoted identifier.
        $db->statement('CREATE DATABASE "'.$database.'" TEMPLATE "'.DisposableE2eDatabase::TEMPLATE.'"');
        $live = $this->live($db, $database);
        $marker = [
            'database' => $database,
            'oid' => $live['oid'] ?? 0,
            'template' => DisposableE2eDatabase::TEMPLATE,
            ...$this->server(),
            'created_at' => now()->toIso8601String(),
        ];
        $path = self::markerPath($database);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, json_encode($marker, JSON_PRETTY_PRINT));
        $this->components->info("Created {$database} from ".DisposableE2eDatabase::TEMPLATE." (OID {$marker['oid']}); marker {$path}");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $protected
     * @param  list<string>  $allowed
     */
    private function drop(string $database, bool $production, array $protected, array $allowed): int
    {
        $db = $this->maintenance();
        $marker = self::readMarker($database);
        $refusal = DisposableE2eDatabase::dropRefusal(
            $database, $production, $protected, $allowed, $marker, $this->live($db, $database),
            $this->server() + ['connected_to' => (string) $db->getDatabaseName()],
        );
        if ($refusal !== null) {
            $this->components->error("Refused: {$refusal}.");

            return self::FAILURE;
        }

        $sql = 'DROP DATABASE "'.$database.'" WITH (FORCE)';
        if (! $this->option('execute')) {
            $this->components->info("Dry run — the evidence matches (OID {$marker['oid']}). With --execute this runs: {$sql}");

            return self::SUCCESS;
        }

        $db->statement($sql);
        @unlink(self::markerPath($database));
        $this->components->info("Dropped {$database}; marker removed.");

        return self::SUCCESS;
    }

    /** The server's `postgres` database, with the credentials of the configured PostgreSQL connection. */
    private function maintenance(): ConnectionInterface
    {
        config(['database.connections.'.self::MAINTENANCE => ['database' => 'postgres'] + config('database.connections.'.config('database.default'))]);

        return DB::connection(self::MAINTENANCE);
    }

    /** @return array{oid: int, is_template: bool}|null */
    private function live(ConnectionInterface $db, string $database): ?array
    {
        $row = $db->selectOne('SELECT oid, datistemplate FROM pg_database WHERE datname = ?', [$database]);

        return $row === null ? null : ['oid' => (int) $row->oid, 'is_template' => (bool) $row->datistemplate];
    }

    /** @return array{host: string, port: string} */
    private function server(): array
    {
        $config = config('database.connections.'.self::MAINTENANCE);

        return ['host' => (string) ($config['host'] ?? ''), 'port' => (string) ($config['port'] ?? '')];
    }

    public static function markerPath(string $database): string
    {
        return storage_path('app/e2e/disposable/'.$database.'.json');
    }

    /** @return array<string, mixed>|null */
    private static function readMarker(string $database): ?array
    {
        $path = self::markerPath($database);
        $marker = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($marker) ? $marker : null;
    }
}
