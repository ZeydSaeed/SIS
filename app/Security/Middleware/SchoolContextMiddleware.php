<?php

namespace App\Security\Middleware;

use App\Database\SchemaHelper;
use App\Security\Context\SchoolContext;
use App\Security\Context\SchoolContextResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

final class SchoolContextMiddleware
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SchoolContextResolver $resolver,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $schoolId = $this->resolver->resolve($request);
        $this->schoolContext->set($schoolId);

        if (SchemaHelper::isPostgreSql()) {
            $value = $schoolId !== null ? (string) $schoolId : '';
            DB::statement("SELECT set_config('app.current_school_id', ?, false)", [$value]);
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $this->schoolContext->clear();

        if (SchemaHelper::isPostgreSql()) {
            DB::statement("SELECT set_config('app.current_school_id', '', false)");
        }
    }
}
