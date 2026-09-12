# MASTER PHASE 7 — PHASE 7.4
# HUMAN IMPLEMENTATION AUTHORIZATION — 7.4-U04

---

```text
Unit: 7.4-U04 — RebuildTermResult
Status: GRANTED
Date: 2026-09-12
Authority: Absolute continuation (“استمر”)
```

## Authorized

```text
- RebuildTermResultCommand / Handler / Result
- Modes: operational | official
- Recompute from LIVE student_grades + structure + locked policies (DL-016)
- If fingerprint matches current row → return existing (no duplicate version)
- If changed → new version via same persist paths as Calculate/Finalize
- TermResultRebuilt outbox event
- Tests + fitness

NOT: Annual, HTTP, GPA, silent overwrite of official without new version
```
