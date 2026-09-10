<?php

/**
 * Phase 3C.12B race worker — insert one completion_outcome and exit 0/1/2.
 * Exit 0 = inserted, 1 = unique_violation 23505, 2 = other error.
 *
 * Args: school_id enrollment_id student_id academic_year_id
 */
require __DIR__.'/../../../vendor/autoload.php';

$schoolId = (int) ($argv[1] ?? 0);
$enrollmentId = (int) ($argv[2] ?? 0);
$studentId = (int) ($argv[3] ?? 0);
$yearId = (int) ($argv[4] ?? 0);
$barrier = $argv[5] ?? null;

if ($schoolId < 1 || $enrollmentId < 1) {
    fwrite(STDERR, "bad args\n");
    exit(2);
}

if (is_string($barrier) && $barrier !== '') {
    $deadline = microtime(true) + 10;
    while (! is_file($barrier) && microtime(true) < $deadline) {
        usleep(1000);
    }
    usleep(random_int(0, 5000));
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$db = getenv('DB_DATABASE') ?: 'sis_test';
$user = getenv('DB_USERNAME') ?: 'postgres';
$pass = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $db),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->exec("SET search_path TO public,organization,academic,students,enrollment,graduation,audit");
    $stmt = $pdo->prepare("SELECT set_config('app.current_school_id', ?, false)");
    $stmt->execute([(string) $schoolId]);

    $ins = $pdo->prepare(
        'INSERT INTO graduation.completion_outcomes
        (school_id, enrollment_id, student_id, academic_year_id, specialization_id, created_at)
        VALUES (?, ?, ?, ?, NULL, NOW())'
    );
    $ins->execute([$schoolId, $enrollmentId, $studentId, $yearId]);
    echo "OK\n";
    exit(0);
} catch (PDOException $e) {
    $code = (string) $e->getCode();
    fwrite(STDERR, $code.' '.$e->getMessage()."\n");
    exit($code === '23505' ? 1 : 2);
}
