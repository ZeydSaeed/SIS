<?php

namespace App\Application\Attendance\Contracts;

/**
 * Runs a read inside one school's tenant context (application school + PostgreSQL RLS setting), restoring the
 * previous context afterwards. Used for the central dashboard: each school is read under its own RLS scope —
 * never by bypassing it.
 */
interface SchoolScopedRunnerInterface
{
    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(int $schoolId, callable $callback): mixed;

    /**
     * @param  list<int>  $schoolIds
     * @return array<int, string>
     */
    public function schoolNames(array $schoolIds): array;
}
