# MASTER PHASE 7 — PHASE 7.5
# DESIGN LOCK

---

```text
Status: LOCKED
Date: 2026-09-12
Ballot: 02 APPLIED
```

## Scope

### In

```text
- results.gpa_results (versioned; year scope; PERCENT_100)
- Calculate / Finalize / Rebuild GPA
- results.ranking_snapshots (versioned comparative projection)
- BuildRankingSnapshot (class/year)
- results.transcripts (issued metadata; supersede)
- IssueTranscript metadata command
```

### Out

```text
- 4.0 grade-point conversion
- Letter bands / credit hours
- PDF/render engine
- HTTP Results/GPA/Ranking/Transcript APIs
- Phase 7.6 read models
- Phase 8
```

## Invariants

| ID | Rule |
|----|------|
| INV-75-01 | Grade SSOT = student_grades only |
| INV-75-02 | GPA/Ranking/Transcript never store raw marks as writable SSOT |
| INV-75-03 | Official GPA: version/supersede only |
| INV-75-04 | Ranking is NOT academic truth (DL-005) |
| INV-75-05 | Transcript issued rows immutable; supersede only |
| INV-75-06 | school_id + FORCE RLS on all new results tables |
| INV-75-07 | Year GPA input = official current annual_results |

## GPA v1 physical intent

```text
results.gpa_results
  school_id, enrollment_id, student_id, academic_year_id
  gpa_scope SMALLINT (1=academic_year)
  result_version, lifecycle_status, is_official, is_current_*
  gpa_value NUMERIC(8,2)
  scale_code VARCHAR(32) DEFAULT 'PERCENT_100'
  source_annual_result_id BIGINT NULL (FK soft/logical)
  source_fingerprint, policy_pin, timestamps...
```

## Unit plan

| Unit | Name | AuthZ |
|------|------|-------|
| **7.5-U01** | gpa_results schema + FORCE RLS | NEXT |
| 7.5-U02 | CalculateGpa | later |
| 7.5-U03 | FinalizeGpa | later |
| 7.5-U04 | RebuildGpa | later |
| 7.5-U05 | ranking_snapshots schema | later |
| 7.5-U06 | BuildRankingSnapshot | later |
| 7.5-U07 | transcripts schema | later |
| 7.5-U08 | IssueTranscript | later |

```text
PHASE 7.5 DESIGN LOCK: LOCKED
NEXT: U01 Implementation AuthZ
```
