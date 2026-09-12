# MASTER PHASE 7 — PHASE 7.4
# HUMAN IMPLEMENTATION AUTHORIZATION — 7.4-U08

---

```text
Unit: 7.4-U08 — RebuildAnnualResult
Status: GRANTED
Date: 2026-09-12
Authority: Absolute continuation (“استمر”)
```

```text
AUTHORIZED:
  - RebuildAnnualResultCommand / Handler / Result
  - Modes: operational | official
  - Recompute from current term_results (ops or official)
  - Fingerprint match → unchanged (no new version)
  - Changed → new annual version
  - AnnualResultRebuilt outbox
  - Tests + fitness

NOT: HTTP, GPA, Ranking, Transcript
```
