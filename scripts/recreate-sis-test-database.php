<?php

/**
 * Recreate disposable sis_test (DROP + CREATE). Never touches sis.
 */

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$user = getenv('DB_USERNAME') ?: 'postgres';
$pass = getenv('DB_PASSWORD') ?: 'root';
$target = getenv('SIS_PGSQL_TEST_DATABASE') ?: 'sis_test';

if (strtolower($target) === 'sis') {
    fwrite(STDERR, "Refusing to recreate protected database sis.\n");
    exit(1);
}

$dsn = "pgsql:host={$host};port={$port};dbname=postgres";
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '.$pdo->quote($target).' AND pid <> pg_backend_pid()');
$pdo->exec('DROP DATABASE IF EXISTS '.$target);
$pdo->exec('CREATE DATABASE '.$target);
echo "recreated {$target}\n";
