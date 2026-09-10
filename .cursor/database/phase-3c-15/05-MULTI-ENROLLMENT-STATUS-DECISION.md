# Phase 3C.15 — Multi-Enrollment Status Decision

## Locked (do not change)

```text
Graduation SSOT identity = school_id + enrollment_id
(HD-39 APPROVED; UNIQUE LIVE)
```

| Fact | Class |
|------|-------|
| Multiple independent outcomes/awards per enrollments allowed | LOCKED (HD-39) |
| Not student_id-only graduation | LOCKED |
| StudentStatus::Graduated is projection, not SSOT | LOCKED (DL-022) |
| LIVE enum values Inactive/Active/Suspended/Graduated/Withdrawn | LOCKED as **code enum**, not as sync policy |
| Auto-sync of `students.status` from awards | **Not present** in app | 

## Open projection questions

For:

```text
Enrollment A = Graduated (award SSOT)
Enrollment B = Active
```

| Question | Classification |
|----------|----------------|
| Is `StudentStatus` Graduated, Active, or other? | **OPEN** |
| Which enrollment controls student-level status? | **OPEN** |
| Can more than one enrollment be graduated (SSOT)? | **LOCKED YES** at enrollment grain; student projection OPEN |
| Does one enrollment graduation affect another? | SSOT: **LOCKED NO**; StudentStatus: OPEN |
| Revocation clear Graduated? | **OPEN** (`SS-REVOKE-CLEAR`) |
| Different schools enrollments | SSOT isolated LOCKED; projection OPEN |
| Is StudentStatus informational only? | **DERIVED** for graduation authority: must not authorize awards (DL-022) — sync semantics still OPEN |
| Is StudentStatus a business invariant for Graduation writes? | Must not be SSOT (**LOCKED**); other uses OPEN |

```text
SS-MULTI = POLICY NOT LOCKED
SS-REVOKE-CLEAR = POLICY NOT LOCKED
```

**Do not infer** sync behavior from the mere existence of `StudentStatus` enum.

## Safe interim (already documented 3C.11A)

Query awards/completion SSOT for graduation UI; **do not** auto-flip `students.status` without human resolution.

## Implementation impact

```text
StudentStatus synchronization consumers = IMPLEMENTATION BLOCKED
Graduation SSOT writes are NOT blocked by SS-MULTI for identity reasons
```
