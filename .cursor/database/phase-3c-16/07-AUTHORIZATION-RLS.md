# Phase 3C.16 — Authorization + RLS

## Application authority

`FailClosedGraduationAuthority`:

1. `SchoolContext.id()` must equal command `schoolId`
2. Action allow-list from `config('sis.graduation.authority.{action}')` — empty = deny
3. `PublishAward` always denied at authority (and handler gates HD-38)

**No invented `graduation.*` Permission.php entries.** Mapping remains CONDITION until HD-31-G.

## SoD

`EvaluatorApproverSeparation::assertDistinct(evaluatorUserId, approverUserId)`  
Approver compared to completion_outcome_versions.created_by (evaluator of that version).

## RLS

Unchanged Option A: ENABLE + FORCE on graduation tables. Application checks do not replace RLS. Background jobs must set SchoolContext + GUC before school-scoped access.
