<?php

namespace App\Domain\Graduation\ValueObjects;

/**
 * Capability keys for GraduationAuthorityPort — not Permission.php catalog entries (HD-31-G OPEN).
 */
enum GraduationAction: string
{
    case EvaluateCompletion = 'evaluate_completion';
    case CreateCompletionOutcome = 'create_completion_outcome';
    case ApproveGraduation = 'approve_graduation';
    case IssueAward = 'issue_award';
    case RevokeAward = 'revoke_award';
    case PublishAward = 'publish_award';
}
