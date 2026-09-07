<?php

$pdo = new PDO(
    'pgsql:host=127.0.0.1;port=5432;dbname=sis',
    'postgres',
    'root',
);

$locks = $pdo->query("
    SELECT l.pid, l.mode, a.state, a.query
    FROM pg_locks l
    JOIN pg_stat_activity a ON a.pid = l.pid
    WHERE l.relation = 'intelligence.optimization_validation_target'::regclass
      AND l.pid <> pg_backend_pid()
")->fetchAll(PDO::FETCH_ASSOC);

foreach ($locks as $lock) {
    $pdo->exec('SELECT pg_terminate_backend('.(int) $lock['pid'].')');
    echo 'Terminated lock holder PID '.$lock['pid'].' mode '.$lock['mode'].PHP_EOL;
}

if ($locks === []) {
    echo 'No foreign locks on validation target.'.PHP_EOL;
}

$all = $pdo->query("
    SELECT pid, state, query
    FROM pg_stat_activity
    WHERE datname = 'sis' AND pid <> pg_backend_pid()
")->fetchAll(PDO::FETCH_ASSOC);

echo 'Active backends: '.count($all).PHP_EOL;
