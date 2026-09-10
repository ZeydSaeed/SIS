# PHASE 3C.11A — STUDENTSTATUS HUMAN DECISION REGISTER

## Locked physical decisions (not reopened)

| Decision | Status |
|----------|--------|
| No new projection table (D-3C10-008) | CLOSED |
| Awards / completion = SSOT; StudentStatus = projection | LOCKED (DL-022) |
| Sync via outbox eventual consistency | Planned |
| Lag SLA | NOT INVENTED |

## Explicitly unresolved (must stay unresolved until human)

| Decision | Current status | Owner | Impact | Blocks empty-schema DDL? | Blocks production auto-sync? |
|----------|----------------|-------|--------|--------------------------|------------------------------|
| Multi-enrollment → student `Graduated` | HUMAN DECISION REQUIRED | HUMAN DECISION REQUIRED | Which enrollment flips student-level status | NO | **YES** |
| Clear Graduated when all awards revoked | HUMAN DECISION REQUIRED | HUMAN DECISION REQUIRED | Revoke consumer behavior | NO | YES |
| Event names for status triggers | PROPOSED | Event governance | Consumer contracts | NO | External YES |

## Silent assumption check

| Assumption | Present in 3C.11? |
|------------|-------------------|
| Any one enrollment award ⇒ student Graduated | **NO** — explicitly forbidden / deferred |
| StudentStatus is graduation SSOT | **NO** |

```text
Gate K: PASS
No BLOCKING FINDING for invented multi-enrollment behavior
```

## Safe interim (until human decides)

Query enrollment/award SSOT for graduation UI; **do not** auto-flip `students.status` in production without HD resolution.
