# 07 — Data Lifecycle

**Status:** Phase 1 design  
**Related:** ADR-020 D5; Constitution non-hard-delete rules

---

## Policy by class

| Class | Examples | Delete policy |
|-------|----------|---------------|
| CRITICAL academic | grades, enrollments, transcripts | Never hard-delete; status / history / effective_to |
| HIGH operational | attendance history, discipline | Never hard-delete; corrections audited |
| RESTRICTED finance/payroll | payments, journals, payroll runs | Immutable or reversal/adjustment only |
| HIGHLY_RESTRICTED | medical, SEN | Never casual delete; retention + access audit |
| Audit | audit_logs, security_audit_logs, outbox processed | Retain per policy; no casual purge |
| Reference | grade_levels, fee_types | Soft deprecate via `status` |
| Ephemeral | jobs, cache, sessions | Framework TTL OK |

---

## Preferred patterns

```text
status + effective_from + effective_to
version / history tables for grade corrections
reversal transactions for finance
archived_at only when semantically archive (not generic SoftDeletes everywhere)
```

Laravel `softDeletes()` is **not** the default for academic tables.

---

## Assessment (D5)

- Grades MUST NOT support destructive hard deletion.  
- Corrections create auditable history (history table and/or audit events).  
- Partitioning grades by `academic_year_id` must preserve queryability of history.

---

## Retention / archive

Cold `archive` schema is optional and only after a retention ADR. Detach partition ≠ delete without approval.

---

## Intelligence boundary

Self-healing must never DELETE/TRUNCATE lifecycle-protected tables.
