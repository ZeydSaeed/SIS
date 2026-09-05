# Index Governance

> Every index must be justified, measured, and reviewable.  
> **Rule:** No index without documented query purpose.  
> **Adaptive rule:** Indexes are governed by query frequency, latency, selectivity, write overhead, and table cardinality — not by static lists forever.

## Why This Exists

```
More indexes → Faster SELECT
            → Slower INSERT/UPDATE
            → More disk
            → More autovacuum work
```

Do **not** convert every slow query into a new index without analysis.

---

## Index Request Template

Before adding any index, document:

| Field | Value |
|-------|-------|
| **Index name** | `ix_enrollments_school_year` |
| **Table** | `enrollment.enrollments` |
| **Columns** | `(school_id, academic_year_id)` |
| **Type** | B-Tree / Partial / Covering (INCLUDE) / BRIN |
| **Query it serves** | School report by year |
| **Expected selectivity** | ~2,250 rows per school/year |
| **Write overhead** | Low — enrollments write once/year per student |
| **Alternative considered** | Existing index on school_id only — insufficient |
| **EXPLAIN before** | Seq Scan on 450K rows |
| **EXPLAIN after** | Index Scan — target |
| **Can be removed?** | No — core report query |
| **Added in migration** | `V00X__...` |
| **Review date** | YYYY-MM-DD |

Store completed templates in migration PR description or team wiki.

---

## Index Types — When to Use

| Type | Use When | Example |
|------|----------|---------|
| B-Tree (single/composite) | FK joins, WHERE, ORDER BY | `(student_id, academic_year_id)` |
| Partial | Filtered subset < 30% of table | `WHERE status = 1` |
| Covering (INCLUDE) | Index-only scan proven | `INCLUDE (student_code, full_name)` |
| UNIQUE | Business constraint | `(session_id, student_id)` |
| BRIN | Append-only time series | `audit_logs.created_at` |

---

## Forbidden Patterns

| ❌ Don't | ✅ Do |
|---------|------|
| Index every FK "just in case" | Index FK columns used in queries |
| Duplicate: `(a)` when `(a,b)` exists | Use composite only |
| Partial index when 98% rows match | Partial when minority subset queried |
| Index on low-cardinality alone | Combine with selective column |
| Add index without EXPLAIN ANALYZE | Measure before and after |

---

## Review Schedule

| Trigger | Action |
|---------|--------|
| New migration with index | PR review + template filled |
| New table / FK added | Impact analysis — index only if JOIN/WHERE proven |
| Quarterly | Review pg_stat_user_indexes unused indexes |
| After bulk import | ANALYZE affected tables |
| dead_pct > 30% | REINDEX CONCURRENTLY |
| Table > 10M rows | Review partition vs new index |
| Student count ±50% from baseline | Capacity review → index reassessment |
| idx_scan = 0 for 90 days | Review/Remove candidate (see adaptive governance) |

---

## Adaptive Index Decisions

| Signal | Decision |
|--------|----------|
| High usage + high latency | Keep or improve |
| High usage + acceptable latency | Keep |
| Zero usage (90+ days) | Review → Remove if confirmed unused |
| Low-cardinality table (< 10K rows) | Defer index unless query proven slow |
| Write-heavy table + marginal benefit | Reject or partial index |

See [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md) § Index Adaptation.

**Evidence required:**

```text
Before: P95 = X ms (EXPLAIN)
After:  P95 = Y ms (EXPLAIN)
Write overhead estimate: Z%
```

---

## Unused Index Detection

```sql
SELECT schemaname, relname, indexrelname, idx_scan, pg_size_pretty(pg_relation_size(indexrelid))
FROM pg_stat_user_indexes
WHERE idx_scan = 0
  AND indexrelname NOT LIKE '%_pkey'
ORDER BY pg_relation_size(indexrelid) DESC;
```

**Do not drop** without confirming no periodic/reporting query uses it.

---

## 45K Baseline — Core Indexes (Starting Point)

See `indexing-matrix.md` for full list. These are **P0 at baseline** — re-evaluate if workload changes:

- `enrollment.enrollments (school_id, academic_year_id)`
- `attendance.records (student_id, attendance_date)`
- `attendance.daily_section_summary (school_id, attendance_date)`
- `exams.student_grades (student_id, academic_year_id)`
- Partial: `students WHERE status = 1`

**Do not remove** without quarterly review + evidence that queries no longer need them.

---

## Related

- [DATABASE-ADAPTIVE-GOVERNANCE.md](./DATABASE-ADAPTIVE-GOVERNANCE.md)
- [indexing-matrix.md](./indexing-matrix.md)
- [DATABASE-GOVERNANCE.md](./DATABASE-GOVERNANCE.md)
- [postgresql-tuning.md](./postgresql-tuning.md)
