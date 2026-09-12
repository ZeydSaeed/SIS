# PHASE PT — DESIGN LOCK

---

```text
Status: LOCKED
Date: 2026-09-12
Ballot: 02 RECOMMENDED SET APPLIED
```

## In (v1)

```text
- Physicalize promotion.rules + promotion.records
- ADD school_id on promotion.records (blueprint delta for RLS)
- FORCE RLS on both tables via school_id
- Reject hard DELETE triggers
- Unique: one decision row per (enrollment_id, academic_year_id)
- promotion_status SMALLINT CHECK (1=Promoted, 2=Retained, 3=Conditional)
- min_gpa / subject thresholds stored on rules; NOT auto-evaluated in v1
- gpa_at_promotion nullable snapshot only
```

## Out

```text
- Transfers schema physicalization / HTTP
- Auto eligibility engine against GPA
- Auto create next-year enrollment / mutate enrollment status
- Graduation coupling writers
- Partitioning
```

## Invariants

| ID | Rule |
|----|------|
| INV-PT-01 | No hard delete of promotion history |
| INV-PT-02 | school_id required + FORCE RLS |
| INV-PT-03 | enrollment_id must belong to same school_id |
| INV-PT-04 | No inventing GPA formula / auto min_gpa gate |
| INV-PT-05 | Controllers thin when HTTP arrives |

## Units

| Unit | Name | Status |
|------|------|--------|
| PT-U01 | Physicalize promotion.rules + records + RLS | CLOSED |
| PT-U02 | Staff HTTP rules + record decision | CLOSED |
| PT-U03 | Transfers design/security ballot | HOLD |
| PT-U04 | Final Closure Gate | CLOSED WITH CONDITIONS |
