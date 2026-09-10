# 04 — Index Strategy

**Status:** Phase 1 design (no new indexes applied)  
**Governance:** ADR-020 mandatory condition #5; `DATABASE-ADAPTIVE-GOVERNANCE.md`; `indexing-matrix.md`

---

## Principles

1. Indexes exist to serve **measured** query patterns — not every FK automatically.  
2. Before adding an index: inventory existing indexes → identify query → `EXPLAIN (ANALYZE, BUFFERS)` → compare.  
3. Prefer composite indexes matching `WHERE` + `ORDER BY` leftmost prefixes.  
4. Partial indexes are preferred for hot status filters (e.g. `WHERE is_current = true`).  
5. GIN/GiST/BRIN only with documented justification.  
6. Phase gates must list any new index with before/after evidence when claiming performance wins.

---

## Baseline (live)

- ~183 indexes observed in Phase 0 catalog (includes PK/unique/partition children).  
- Attendance records already indexed for school/date, student/date, year/date, session+student uniqueness.  
- Do **not** duplicate these in later phases without proving a gap.

---

## Default candidates (when tables exist)

| Pattern | Typical index |
|---------|---------------|
| Tenant filter | `(school_id)` or `(school_id, academic_year_id)` |
| Student timeline | `(student_id, …date…)` |
| Enrollment lookup | unique / btree on `(student_id, academic_year_id, …)` per blueprint |
| RLS filter columns | Must be indexed when RLS enabled |
| Append-only time scans | Consider BRIN(`created_at`) after volume evidence |

---

## Forbidden in Phase 1+

- Speculative indexes “for enterprise completeness”  
- Indexing every FK blindly  
- Replacing a working composite with overlapping single-column indexes without proof  

---

## Phase checklist (when implementing indexes)

```text
[ ] Query text + calling feature documented
[ ] Existing indexes listed for the table
[ ] EXPLAIN ANALYZE before
[ ] Migration additive only
[ ] EXPLAIN ANALYZE after
[ ] Blueprint / indexing-matrix updated
[ ] Gate report includes evidence
```
