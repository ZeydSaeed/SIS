<?php

namespace App\Security\Middleware;

use App\Security\Audit\Contracts\SecurityAuditLoggerInterface;
use App\Security\Audit\SecurityEventType;
use App\Security\Context\SchoolContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireSchoolContextMiddleware
{
    public function __construct(
        private readonly SchoolContext $schoolContext,
        private readonly SecurityAuditLoggerInterface $securityAudit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null) {
            return $next($request);
        }

        if ($this->schoolContext->id() === null) {
            $this->securityAudit->record(
                SecurityEventType::PolicyBlock,
                'security.require_school_context',
                'denied',
                $request->user(),
                null,
                ['reason' => 'missing_school_context'],
            );

            return response()->json([
                'message' => 'School context is required.',
                'error_code' => 'security.school_context_required',
            ], 403);
        }

        return $next($request);
    }
}
