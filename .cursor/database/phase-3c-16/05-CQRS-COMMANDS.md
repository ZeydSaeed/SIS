# Phase 3C.16 — CQRS Commands

| Command | Handler | Auth action key | Outbox event | Notes |
|---------|---------|-----------------|--------------|-------|
| CreateCompletionOutcome | CreateCompletionOutcomeHandler | create_completion_outcome | CompletionOutcomeCreated | UNIQUE school+enrollment |
| EvaluateCompletion | EvaluateCompletionHandler | evaluate_completion | CompletionEvaluated | Pins policy version id |
| ApproveGraduation | ApproveGraduationHandler | approve_graduation | GraduationApproved | SoD + eligibility_status=2 |
| IssueAward | IssueAwardHandler | issue_award | AwardIssued | Requires approved approval |
| RevokeAward | RevokeAwardHandler | revoke_award | AwardRevoked | Opaque reason_ref |
| PublishAward | PublishAwardHandler | — | — | Always throws PublicationPolicyNotConfiguredException |

Authority keys are **config allow-list slots**, not Permission.php strings.
