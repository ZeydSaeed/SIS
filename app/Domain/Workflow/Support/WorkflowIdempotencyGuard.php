<?php

namespace App\Domain\Workflow\Support;

use App\Domain\Workflow\Exceptions\MissingWorkflowIdempotencyKeyException;

final class WorkflowIdempotencyGuard
{
    public static function requireKey(?string $key): string
    {
        $trimmed = trim((string) $key);
        if ($trimmed === '') {
            throw MissingWorkflowIdempotencyKeyException::required();
        }

        return $trimmed;
    }
}
