# MASTER PHASE 7 — PHASE 7.6
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
- CQRS Queries (Application) over LIVE results.* tables
- Official-current Term / Annual / Year GPA reads
- Current Ranking snapshot read (comparative label mandatory)
- Issued current Transcript metadata read
- PG integration tests + architecture fitness
```

### Out

```text
- New SSOT / MV / denormalized grade ledgers
- Student/guardian HTTP portal
- PDF/render engine
- Timetable HTTP
- Phase 8
- 4.0 / letters / credits invention
```

## Invariants

| ID | Rule |
|----|------|
| INV-76-01 | Grade SSOT remains `exams.student_grades` only |
| INV-76-02 | Reads never mutate; no side-effect queries |
| INV-76-03 | Default official-current only unless explicit operational query (later) |
| INV-76-04 | Ranking DTO labeled comparative / not academic truth |
| INV-76-05 | Transcript read = issued metadata; no PDF bytes |
| INV-76-06 | Tenant isolation via school_id + existing FORCE RLS |
| INV-76-07 | Blueprint object count unchanged (90) |

## Unit plan

| Unit | Name | AuthZ |
|------|------|-------|
| **7.6-U01** | GetOfficialTermResult | NEXT |
| 7.6-U02 | GetOfficialAnnualResult | later |
| 7.6-U03 | GetOfficialYearGpa | later |
| 7.6-U04 | GetCurrentRankingSnapshot | later |
| 7.6-U05 | GetIssuedTranscriptMetadata | later |
| 7.6-U06 | Final Closure Gate | later |

```text
PHASE 7.6 DESIGN LOCK: LOCKED
NEXT: 7.6-U01 Human Implementation Authorization Request
Implementation remains BLOCKED until unit AuthZ GRANTED
```
