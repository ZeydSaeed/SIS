# MASTER PHASE 7 — PHASE 7.8
# READINESS DISCOVERY

---

```text
Date: 2026-09-12
Mode: READ-ONLY
Predecessor: Phase 7.6 CLOSED; TV-U11 CLOSED; Phase 8 HOLD
```

## Live inputs (reuse)

| Surface | Status |
|---------|--------|
| Application official Term/Annual/GPA/Transcript queries | LIVE (7.6-U01–U05) |
| Staff JSON readers (`results.view`) | LIVE (7.6-U06) |
| `results.*` FORCE RLS | LIVE |
| `guardians.student_guardians` | LIVE (relationship) |
| `security.scopes` | LIVE (unused application binding) |

## Gaps (P0)

| Gap | Observed |
|-----|----------|
| `students.students.user_id` | **ABSENT** (unlike `teachers.teachers.user_id`) |
| `guardians.guardians.user_id` | **ABSENT** |
| Student/guardian HTTP results routes | **ABSENT** |
| Portal permission vocabulary | **ABSENT** |
| Application usage of `security.scopes` for party bind | **ABSENT** |

```text
BLOCKER without design decision:
Cannot safely bind authenticated User → Student/Guardian party.
```

## Recommended unblock (no new table)

```text
Use existing security.scopes:
  scope_type = 'student'  → scope_id = students.students.id
  scope_type = 'guardian' → scope_id = guardians.guardians.id
Guardian expansion: guardian scope + student_guardians → allowed student_ids
School: user_roles.school_id + X-School-Id (existing SchoolContext)
```

## Inheritance

| Source | Use |
|--------|-----|
| P7-D10 | Ownership server-side; guardian relationship verified |
| 7.6 INV | Official-current only; ranking comparative; no PDF |
| Staff `results.view` | **MUST NOT** be reused alone for portal (broader than self) |

## Absolute prohibitions

```text
- No email/national_id soft-match identity
- No staff results.view as portal sole AuthZ
- No ranking class roster on portal v1
- No operational/non-official results on portal v1
- No Phase 8
- No second grade ledger
```
