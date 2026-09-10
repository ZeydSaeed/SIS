# PHASE 3C.11 — STUDENTSTATUS INTEGRATION PLAN

**Do not modify StudentStatus in this phase. No new projection table (D-3C10-008).**

## Current LIVE state (audit)

| Item | Finding |
|------|---------|
| Implementation | `App\Domain\Student\ValueObjects\StudentStatus` backed by student row `status` SMALLINT |
| Values | Inactive=0, Active=1, Suspended=2, **Graduated=3**, Withdrawn=4 |
| Graduation sync | **Not present** — no outbox consumer from awards |
| SSOT for graduation | **Not** StudentStatus — awards are SSOT (DL-022) |

## Target integration (future)

```text
source_of_truth: graduation.graduation_award_versions (current issued, non-revoked)
projection: students.students.status → Graduated
mechanism: outbox consumer (eventual)
NO NEW TABLE
```

| Concern | Plan |
|---------|------|
| Event trigger | `graduation.award_issued` / `graduation.award_revoked` (PROPOSED names) |
| Update mechanism | Application handler in Students context updates status via repository — **not** graduation SSOT write |
| Transaction boundary | **Eventually consistent** — NOT same DB transaction as award insert (avoid distributed TX); outbox processed async (existing ProcessOutboxJob cadence) |
| Rebuild | Command: scan current issued awards → set Graduated; clear Graduated if no current award (rules TBD without inventing multi-enrollment edge cases — **HUMAN** if conflicts) |
| Replay | Reprocess outbox or rebuild from SSOT |
| Failure | Outbox retries; projection may lag; UI must not treat status as sole truth for certificates |
| Idempotency | Consumer idempotent on award_version_id |
| Audit | Student status change stages its own audit/outbox if existing pattern requires |
| Stale detection | Compare status Graduated vs exists current award for enrollment/student — ops query |
| Lag policy | **NOT INVENTED** |

## Multi-enrollment caveat

HD-39: outcomes are enrollment-scoped. Student may have multiple enrollments. Setting student-level `Graduated` when **one** enrollment awards requires **BUSINESS POLICY DEPENDENCY** for which enrollment drives student-level status.

```text
BUSINESS POLICY DEPENDENCY — HUMAN DECISION REQUIRED
(before production StudentStatus auto-sync rules)
```

Until decided: plan may sync only under explicit school policy hook — or limit projection to enrollment-level UI queries against awards SSOT without flipping student.status.

## Tests (future)

projection update; stale; replay; rebuild; event failure; never treat status as award SSOT.
