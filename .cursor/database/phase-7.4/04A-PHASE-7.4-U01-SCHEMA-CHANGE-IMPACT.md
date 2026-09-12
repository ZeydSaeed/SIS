# MASTER PHASE 7 — PHASE 7.4-U01
# SCHEMA CHANGE IMPACT (CHECKLIST)

---

```text
Change: CREATE results.term_results + FORCE RLS
Class: Medium (new table + RLS)
Date: 2026-09-12
AuthZ: 04 GRANTED
```

---

## Checklist

- [x] Fits `results` schema / Phase 7.4 Design Lock
- [x] 3NF — versioned derived facts; no repeating grade scores as SSOT
- [x] PK BIGINT IDENTITY
- [x] `academic_year_id` + `school_id` present
- [x] FKs RESTRICT (academic)
- [x] Indexes: identity+version UNIQUE; current ops/official partial; read paths
- [x] No TINYINT — SMALLINT for status/pass_fail
- [x] TIMESTAMPTZ timestamps
- [x] No hard-delete — reject trigger
- [x] No partition (3C.2 / Design Lock v1)
- [x] Blueprint updated (same object `term_results`, superseded sketch)
- [x] Object count remains 87
- [x] RLS ENABLE + FORCE + school policy
- [ ] Migration applied + PG tests (next)

## Blast radius

```text
NEW: results.term_results
TOUCH: academic.terms unique (id, academic_year_id) support index
NO CHANGE: exams.student_grades SSOT
NO: annual_results / transcripts / GPA
```
