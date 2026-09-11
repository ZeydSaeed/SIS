# Feature: Graduation

> Phase 3C.16 application write path (CQRS). Permission catalog HD-31-G remains OPEN — HTTP/Permission.php not invented.

## Bounded Context

- **Context:** Graduation
- **Primary aggregate:** CompletionOutcome / GraduationApproval / GraduationAward (enrollment-scoped)
- **Scoped by:** `school_id` + `enrollment_id` (never student-only identity)

## Planned Use Cases

| Type | Name | Status |
|------|------|--------|
| Command | CreateCompletionOutcome | ✅ |
| Command | EvaluateCompletion | ✅ (policy-row driven; no invented thresholds) |
| Command | ApproveGraduation | ✅ (SoD Evaluator≠Approver) |
| Command | IssueAward | ✅ |
| Command | RevokeAward | ✅ (opaque reason_ref; catalog OPEN) |
| Command | PublishAward | ⛔ policy-gated HD-38 |
| Command | RevokeGraduation (approval) | ⛔ HD-36 / HD-31 OPEN |
| Query | GetCompletionStatus | ✅ UNIT-3C19-01 |
| Query | GetRequirementEvaluations | ✅ UNIT-3C19-02 |
| Query | GetGraduationApproval | ✅ UNIT-3C19-03 |
| Query | GetGraduationAward | ✅ UNIT-3C19-04 (pointer-only version) |
| Query | GetOutcomeHistory | ✅ UNIT-3C19-05 (historical aggregate; Design Lock) |
| Projection | StudentStatus sync | ⛔ NOT IMPLEMENTED (DL-022) |

## Non-negotiables

- In-transaction: idempotency + business write + outbox
- RLS + SchoolContext; fail-closed authority allow-lists
- No StudentStatus auto-flip; no hard delete
