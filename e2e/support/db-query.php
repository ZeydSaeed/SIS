<?php

/**
 * Read-only database access for the e2e suite (assertions only — the tests change data through the UI).
 *
 * stdin: {"sql": "SELECT …", "bindings": [...]}  ·  stdout: the rows as JSON.
 * Refuses any database whose name does not start with sis_e2e, anything but one SELECT / WITH statement, and runs
 * inside a READ ONLY transaction that is always rolled back.
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = DB::connection();
$database = strtolower((string) $connection->getDatabaseName());
if (preg_match('/^sis_e2e[a-z0-9_]*$/', $database) !== 1) {
    fwrite(STDERR, "db-query: refused — «{$database}» is not an e2e database.\n");
    exit(2);
}

$input = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$sql = trim((string) ($input['sql'] ?? ''));
if (preg_match('/^(select|with)\b/i', $sql) !== 1 || str_contains(rtrim($sql, "; \n\t"), ';')) {
    fwrite(STDERR, "db-query: only a single SELECT statement is allowed.\n");
    exit(2);
}

$connection->beginTransaction();
try {
    $connection->statement('SET TRANSACTION READ ONLY');
    $rows = $connection->select($sql, array_values($input['bindings'] ?? []));
} finally {
    $connection->rollBack();
}

echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
