# MASTER PHASE 7 — PHASE 7.6
# HUMAN DESIGN DECISION BALLOT → RECORDED

---

```text
Date: 2026-09-12
Authority: Human “choose best” → RECOMMENDED SET APPLIED
Implementation: NOT AUTHORIZED until Design Lock + unit AuthZ
```

---

### HD-7.6-001 — Subphase scope

```text
[x] A — Application read queries for official Term/Annual/GPA + Ranking snapshot
        + issued Transcript metadata (CQRS Queries; no new SSOT tables)
[ ] B — Student/guardian HTTP grade portal in 7.6
[ ] C — Defer entire 7.6
```

---

### HD-7.6-002 — Storage strategy

```text
[x] A — Query LIVE results.* / exams.student_grades (no new MV in v1)
[ ] B — Create reporting MVs now
[ ] C — Duplicate denormalized read tables
```

---

### HD-7.6-003 — Audience v1

```text
[x] A — Staff/admin Application queries + PG tests only (no student HTTP yet)
[ ] B — Student/guardian HTTP in first units
[ ] C — Public unauthenticated reads — FORBIDDEN
```

**Rationale:** P7-D10 security classification — open student portal only after dedicated AuthZ/privacy ballot.

---

### HD-7.6-004 — Official vs operational

```text
[x] A — Default reads return current official (is_current_official / issued current)
        Operational/calculated-only exposed only via explicit query flag or separate query
[ ] B — Mix official+operational without labeling
```

---

### HD-7.6-005 — Ranking DTO labeling

```text
[x] A — DTO must mark ranking as comparative projection (not academic truth)
[ ] B — Present ranks as official academic standing
```

---

### HD-7.6-006 — Student grade history

```text
[x] B — DEFER GetStudentGradeHistory HTTP; optional Application query later unit if AuthZ
[ ] A — Full student grade portal now
```

---

### HD-7.6-007 — HTTP

```text
[x] B — No HTTP Results/GPA/Ranking/Transcript readers in 7.6 first units
[ ] A — HTTP in first units
```

---

### HD-7.6-008 — Unit sequence

```text
[x] A — U01 GetOfficialTermResult → U02 GetOfficialAnnualResult → U03 GetOfficialYearGpa
         → U04 GetCurrentRankingSnapshot → U05 GetIssuedTranscriptMetadata
         → U06 Progress/Final gate (HTTP deferred)
[ ] B — Big-bang all queries one unit
```

---

### HD-7.6-009 — Phase 8 / Timetable HTTP

```text
[x] A — Remain HOLD
[ ] B — Open in parallel — FORBIDDEN without separate AuthZ
```

---

## RECOMMENDED SET (APPLIED)

| ID | Choice |
|----|--------|
| 001 | A — official Results/GPA/Ranking/Transcript queries |
| 002 | A — LIVE tables, no MV |
| 003 | A — staff Application only |
| 004 | A — official-current default |
| 005 | A — ranking labeled comparative |
| 006 | B — defer student grade portal |
| 007 | B — no HTTP first units |
| 008 | A — staged units |
| 009 | A — Phase 8 / TV HTTP hold |

```text
BALLOT STATUS: RECORDED / APPLIED
Continue → Design Lock
```
