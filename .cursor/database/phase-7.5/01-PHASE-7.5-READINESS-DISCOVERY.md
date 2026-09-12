# MASTER PHASE 7 — PHASE 7.5
# READINESS DISCOVERY

---

```text
Date: 2026-09-12
Mode: READ-ONLY
Predecessor: Phase 7.4 CLOSED WITH CONDITIONS (25)
```

## Live

| Check | Observed |
|-------|----------|
| `results.term_results` | LIVE + FORCE RLS |
| `results.annual_results` | LIVE + FORCE RLS |
| GPA / ranking / transcript tables | **ABSENT** |
| Blueprint `results.transcripts` | Sketch only (87 objects) |
| Grade SSOT | `exams.student_grades` |

## Design inheritance

| Source | Use |
|--------|-----|
| 3C.4 GPA architecture | Primary GPA model |
| 3C.5 Ranking snapshots | Ranking (not columns on annual) |
| 3C.6 Transcript hybrid | Issued artifact + live projection |
| HD-01…15 mostly UNRESOLVED | Ballot must lock v1 minima |

## Work packages (post Lock)

| ID | Package |
|----|---------|
| WP-75-01 | Versioned `results.gpa_results` + FORCE RLS |
| WP-75-02 | CalculateGpa / FinalizeGpa / RebuildGpa (year scope) |
| WP-75-03 | `results.ranking_snapshots` + FORCE RLS |
| WP-75-04 | BuildRankingSnapshot (section/year) |
| WP-75-05 | Physicalize `results.transcripts` issued metadata |
| WP-75-06 | IssueTranscript (no PDF engine in first unit) |

## Prohibitions

```text
No second grade ledger
No rank_* columns on term/annual
No silent official mutate
No HTTP in first units
No Phase 18 MV substitution
```
