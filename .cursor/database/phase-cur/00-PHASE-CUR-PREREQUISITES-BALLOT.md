# PHASE CUR — SUBJECT PREREQUISITES BALLOT

---

```text
Date: 2026-09-13
Human: «استمر» (post Phase 8.6 — blueprint gap curriculum.prerequisites)
Unit: CUR-U01 Add / List / Soft-deactivate subject prerequisites
Status: LOCKED
Schema: CREATE curriculum.prerequisites (+ status soft lifecycle)
```

## Decisions

| ID | Topic | Choice |
|----|-------|--------|
| HD-CUR-001 | Table | Physicalize blueprint `curriculum.prerequisites` |
| HD-CUR-002 | Soft lifecycle | ADD `status` SMALLINT 1=Active 2=Inactive (blueprint amendment) — no hard DELETE |
| HD-CUR-003 | Self-edge | REJECT (`subject_id <> prerequisite_subject_id`) |
| HD-CUR-004 | Cycles | Reject if adding edge creates a cycle among **active** edges |
| HD-CUR-005 | Re-add | Reactivate inactive unique row |
| HD-CUR-006 | Tenant | Global catalog (no `school_id`); HTTP still requires school context + `curriculum.manage` |
| HD-CUR-007 | Auth | `curriculum.view` / `curriculum.manage` |
| HD-CUR-008 | HTTP | `POST/GET …/subjects/{id}/prerequisites`; `POST …/prerequisites/{id}/deactivate` |
| HD-CUR-009 | Idempotency | Required on writes |
| HD-CUR-010 | Subjects CRUD / curricula HTTP | HOLD (out of this unit) |
| HD-CUR-011 | Payroll / exam.session.cancel / Ranking-PDF | Absolute HOLD elsewhere |
