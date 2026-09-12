# PHASE PT — HUMAN DESIGN DECISION BALLOT → RECORDED

---

```text
Date: 2026-09-12
Authority: Absolute continuation — RECOMMENDED SET applied
Implementation: NOT until Design Lock + unit AuthZ
```

## HD-PT-001 — Scope split

```text
[x] A — Promotion first (physicalize + staff CQRS later); Transfers HOLD
[ ] B — Promotion + Transfers schema together, no HTTP
[ ] C — Full lifecycle (auto next enrollment + transfer complete) — FORBIDDEN without ballots
```

## HD-PT-002 — GPA / min_gpa enforcement

```text
[x] A — Store rule thresholds + optional gpa_at_promotion snapshot; NO auto eligibility engine in v1
[ ] B — Enforce min_gpa against results.year_gpa automatically — BLOCKED (scale/policy risk)
[ ] C — Drop min_gpa column — REJECTED (keep blueprint column, nullable)
```

## HD-PT-003 — school_id on promotion.records

```text
[x] A — ADD school_id + FORCE RLS (align with results.* tenant pattern; blueprint delta)
[ ] B — RLS via enrollment join only — REJECTED (fragile / no WITH CHECK simplicity)
```

## HD-PT-004 — Enrollment side effects

```text
[x] A — Record decision ONLY; do NOT close enrollment / create next-year enrollment in v1
[ ] B — Auto-promote creates next enrollment — DEFERRED
```

## HD-PT-005 — Hard delete

```text
[x] A — Reject hard DELETE on promotion.rules + promotion.records
[ ] B — Allow delete — FORBIDDEN for academic decision history
```

## HD-PT-006 — Transfers

```text
[x] A — HOLD physicalization until PT-U03 AuthZ (cross-school security ballot)
[ ] B — Physicalize transfers tables now without HTTP
[ ] C — Implement transfer complete HTTP now — FORBIDDEN
```
