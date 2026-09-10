<?php

/**
 * Create disposable PostgreSQL database sis_test if missing.
 * Connects to maintenance DB "postgres" — never drops or truncates "sis".
 */

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$user = getenv('DB_USERNAME') ?: 'postgres';
$pass = getenv('DB_PASSWORD') ?: 'root';
$target = getenv('SIS_PGSQL_TEST_DATABASE') ?: 'sis_test';

if (strtolower($target) === 'sis') {
    fwrite(STDERR, "Refusing to create protected name sis as a test database.\n");
    exit(1);
}

$dsn = "pgsql:host={$host};port={$port};dbname=postgres";
$pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$exists = $pdo->query('SELECT 1 FROM pg_database WHERE datname = '.$pdo->quote($target))->fetchColumn();
if ($exists) {
    echo "exists {$target}\n";
    exit(0);
}

$pdo->exec('CREATE DATABASE '.$target);
echo "created {$target}\n";
