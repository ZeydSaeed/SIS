# Phase 3C.15A — Multi-Enrollment Final Status Policy

## LOCKED

```text
Graduation SSOT identity = school_id + enrollment_id
Multiple independent Graduation SSOT records per enrollments = ALLOWED
StudentStatus::Graduated is projection, not SSOT (DL-022)
Graduation of Enrollment A does not mutate Enrollment B SSOT = NO effect on B's SSOT
```

## OPEN — StudentStatus projection (NOT PROVIDED)

| ID | Question | Human Decision |
|----|----------|----------------|
| SS-MULTI-01 | `students.status` when A Graduated & B Active | **NOT PROVIDED** → OPEN |
| SS-MULTI-02 | Which enrollment(s) influence student-level status? | **NOT PROVIDED** → OPEN |
| SS-MULTI-03 | Can multiple enrollments be Graduated at projection level simultaneously? | **NOT PROVIDED** → OPEN (SSOT yes LOCKED) |
| SS-MULTI-04 | Does graduation of A affect B at projection/business level beyond SSOT? | SSOT: **LOCKED NO**; projection extras: **OPEN** |
| SS-REVOKE-CLEAR | Auto leave Graduated when last contributing award revoked? | **NOT PROVIDED** → OPEN |

```text
MULTI-ENROLLMENT: PARTIAL
StudentStatus sync: BLOCKED
```
