# Phase 3C.15 — E2E Policy Test Matrix (Prepared, Not Executed)

Graduation SSOT identity remains `school_id + enrollment_id` in all scenarios.

| Scenario | Description | Graduation SSOT result | StudentStatus result | Authorization result | Audit / lineage |
|----------|-------------|------------------------|----------------------|----------------------|-----------------|
| A | One active enrollment | Outcome/award for that enrollment only | EXPECTED = **POLICY NOT LOCKED** if sync on | Authz = **POLICY NOT LOCKED** for approve | Lineage per enrollment |
| B | Two active enrollments same school | Independent SSOT rows allowed (HD-39) | **POLICY NOT LOCKED** | Per-school; permissions OPEN | Separate lineages |
| C | Enrollments in different schools | Isolated by school (RLS/FK) | **POLICY NOT LOCKED** | Cross-school **DENIED** (LOCKED) | No cross-school edges |
| D | A graduated, B active | A may hold award; B independent | **POLICY NOT LOCKED** (SS-MULTI) | OPEN for who issued A | A lineage preserved |
| E | A revoked, B active | A revoked lineage; B untouched | **POLICY NOT LOCKED** (SS-REVOKE-CLEAR) | HD-36-ROLES OPEN | Revocation record required |
| F | Two independently graduated enrollments | Both awards allowed at SSOT | **POLICY NOT LOCKED** | OPEN | Two lineages |
| G | One revoke while other graduated remains | Revoked one lineage; other current | **POLICY NOT LOCKED** | OPEN | No destructive delete |

```text
EXPECTED RESULT = POLICY NOT LOCKED
```

applies to all StudentStatus projection cells until SS-MULTI / SS-REVOKE-CLEAR are human-approved.

Do not execute as implementation tests in 3C.15.
