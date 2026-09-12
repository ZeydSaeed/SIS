# MASTER PHASE 7 — PHASE 7.8
# HUMAN PRIVACY DESIGN DECISION BALLOT → RECORDED

---

```text
Date: 2026-09-12
Authority: Human «استمر بما هو الافضل» → RECOMMENDED SET APPLIED
Implementation: NOT AUTHORIZED until Design Lock + unit AuthZ
```

---

### HD-7.8-001 — Subphase scope

```text
[x] A — Student + guardian official-current Term/Annual/GPA + issued Transcript metadata
[ ] B — Full grade history + ranking + PDF in v1
[ ] C — Defer entire portal
```

---

### HD-7.8-002 — Identity binding

```text
[x] A — security.scopes (student|guardian) — no new table / no user_id columns in v1
[ ] B — Add nullable user_id on students + guardians (teachers pattern)
[ ] C — Invent parallel identity table
```

---

### HD-7.8-003 — Audience AuthZ

```text
[x] A — Dedicated permission portal.results.view + ownership checks
[ ] B — Reuse staff results.view alone — FORBIDDEN
[ ] C — Public unauthenticated — FORBIDDEN
```

---

### HD-7.8-004 — Official vs operational

```text
[x] A — Official-current / issued only (reuse 7.6 queries)
[ ] B — Operational calculated rows on portal
```

---

### HD-7.8-005 — Ranking

```text
[x] B — OUT of portal v1 (peer comparative leakage)
[ ] A — Include ranking with comparative label
```

---

### HD-7.8-006 — Guardian rule

```text
[x] A — Guardian scope_id + active student_guardians link required
[ ] B — Any guardian in school may read any student — FORBIDDEN
```

---

### HD-7.8-007 — HTTP prefix

```text
[x] A — /api/v1/portal/results/*
[ ] B — Same paths as staff /api/v1/results/* with dual AuthZ — REJECTED
```

---

### HD-7.8-008 — Scope-link admin API

```text
[x] B — DEFER admin link/unlink HTTP; test inserts + future security unit
[ ] A — Admin link API in U01
```

---

### HD-7.8-009 — Phase 8

```text
[x] A — Remain HOLD
[ ] B — Open in parallel — FORBIDDEN
```

---

### HD-7.8-010 — Unit sequence

```text
[x] A — U01 Portal ownership + official Term/Annual/GPA/Transcript HTTP
         → U02 Guardian-path hardening tests / audit
         → U03 Final gate (ranking/PDF/admin-link deferred)
[ ] B — Big-bang with ranking + PDF
```

```text
BALLOT STATUS: RECORDED / APPLIED (recommended set)
NEXT: Design Lock → U01 AuthZ
```
