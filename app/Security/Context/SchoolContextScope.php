<?php

namespace App\Security\Context;

use App\Database\SchemaHelper;
use Illuminate\Support\Facades\DB;

/**
 * Runs queued / event-driven work inside a school context — the same binding
 * SchoolContextMiddleware applies to HTTP requests (app SchoolContext + PostgreSQL
 * app.current_school_id for RLS). The previous context is restored afterwards.
 */
final class SchoolContextScope
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
    ) {}

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(int $schoolId, callable $callback): mixed
    {
        $previous = $this->schoolContext->id();
        $this->bind($schoolId);

        try {
            return $callback();
        } finally {
            $this->bind($previous);
        }
    }

    private function bind(?int $schoolId): void
    {
        $this->schoolContext->set($schoolId);

        if (SchemaHelper::isPostgreSql()) {
            DB::statement(
                "SELECT set_config('app.current_school_id', ?, false)",
                [$schoolId !== null ? (string) $schoolId : ''],
            );
        }
    }
}
