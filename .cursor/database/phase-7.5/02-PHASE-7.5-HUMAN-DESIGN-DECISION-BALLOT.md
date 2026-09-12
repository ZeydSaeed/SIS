# MASTER PHASE 7 — PHASE 7.5
# HUMAN DESIGN DECISION BALLOT → RECORDED

---

```text
Date: 2026-09-12
Authority: Absolute continuation — RECOMMENDED SET APPLIED
Implementation: NOT AUTHORIZED until Design Lock + unit AuthZ
```

---

### HD-7.5-001 — Subphase scope

```text
[x] A — GPA (year) + Ranking snapshots + Transcript issued metadata (staged units)
[ ] B — GPA only in 7.5
[ ] C — Defer entire 7.5
```

---

### HD-7.5-002 — GPA scale / formula (maps HD-01) — v1

```text
[x] A — Year-scope GPA value = official annual average_weighted_total (0–100 scale)
        stored as NUMERIC(8,2); label scale_code='PERCENT_100'; 4.0 conversion DEFERRED
[ ] B — Invent 4.0 grade-point conversion now
[ ] C — Defer all GPA DDL
```

**Rationale:** Avoid fabricating letter/credit/4.0 rules; year GPA already computable from 7.4 annual official.

---

### HD-7.5-003 — GPA input source

```text
[x] A — Official current annual_results only for year-scope GPA
[ ] B — Recompute solely from grades (skip annual)
[ ] C — Operational annual allowed for official GPA
```

---

### HD-7.5-004 — Letter bands (HD-02)

```text
[ ] A — Lock letter bands now
[x] B — DEFER letters — no letter column on gpa_results v1
```

---

### HD-7.5-005 — Credits (HD-15)

```text
[x] B — DEFER credit-hours — not required for PERCENT_100 year GPA v1
```

---

### HD-7.5-006 — Ranking scope (HD-08) — v1

```text
[x] A — School + academic_year + class_id (from enrollment) ordered by year GPA DESC
[ ] B — Section only
[ ] C — Defer ranking DDL
```

---

### HD-7.5-007 — Ranking ties (HD-09)

```text
[x] A — Dense rank; ties share rank; next rank skips (1,2,2,4)
[ ] B — Competition rank without skip
[ ] C — Defer
```

---

### HD-7.5-008 — Ranking privacy (HD-10)

```text
[x] B — No student HTTP ranking API in 7.5; Application command + tests only
```

---

### HD-7.5-009 — Ranking metric

```text
[x] A — Official year GPA (PERCENT_100) as ranking metric
[ ] B — Annual average directly (duplicate)
```

---

### HD-7.5-010 — Transcript packaging (HD-16)

```text
[x] A — 7.5 = issued transcript metadata + payload_hash + storage_key nullable
        PDF/render/content engine = DEFERRED (3D / later unit)
[ ] B — Full PDF issuance in 7.5
```

---

### HD-7.5-011 — Issued transcript after correction (HD-11)

```text
[x] A — Supersede issued transcript (new version); never mutate prior issued row
[ ] B — Allow in-place mutate — FORBIDDEN
```

---

### HD-7.5-012 — HTTP

```text
[x] B — No HTTP writers for GPA/Ranking/Transcript in 7.5 first units
```

---

### HD-7.5-013 — Unit sequence

```text
[x] A — U01 gpa schema → U02 CalculateGpa → U03 FinalizeGpa → U04 RebuildGpa
         → U05 ranking schema → U06 BuildRanking → U07 transcripts schema → U08 IssueTranscript
```

---

## RECOMMENDED SET (APPLIED)

| ID | Choice |
|----|--------|
| 001 | A |
| 002 | A — PERCENT_100 from official annual |
| 003 | A |
| 004 | B — defer letters |
| 005 | B — defer credits |
| 006 | A — class/year ranking |
| 007 | A — dense rank |
| 008 | B — no HTTP |
| 009 | A |
| 010 | A — metadata only |
| 011 | A — supersede |
| 012 | B |
| 013 | A |

```text
BALLOT STATUS: RECORDED / APPLIED
Continue → Design Lock
```
