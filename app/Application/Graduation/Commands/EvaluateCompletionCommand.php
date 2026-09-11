<?php

namespace App\Application\Graduation\Commands;

use App\Application\Contracts\Command;

/**
 * @param  list<array{requirement_definition_version_id:int,result_status:int}>  $requirementResults
 * result_status: 1=satisfied, 2=not_satisfied, 3=missing_evidence (DL-020: missing ≠ satisfied)
 */
final readonly class EvaluateCompletionCommand implements Command
{
    /**
     * @param  list<array{requirement_definition_version_id:int,result_status:int}>  $requirementResults
     */
    public function __construct(
        public int $schoolId,
        public int $enrollmentId,
        public int $eligibilityPolicyVersionId,
        public array $requirementResults,
        public string $calculationVersion,
        public int $actorUserId,
        public string $idempotencyKey,
        public string $correlationId,
    ) {}
}
