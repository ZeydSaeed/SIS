# PHASE 7.8 — U02 HUMAN IMPLEMENTATION AUTHORIZATION

---

```text
Date: 2026-09-12
Human: «استمر»
Selected: 7.8-U02 Guardian path + deny-matrix hardening
Rejected for now: Admin scope-link HTTP · Ranking · PDF · Phase 8
Status: GRANTED
```

## Scope (IN)

```text
Refactor PortalPartyAccessService to per-student ownership checks
(direct student scope OR guardian scope + student_guardians)
Deny-matrix PG tests:
  - guardian scope without student_guardians → 403
  - student A cannot read student B → 403
  - guardian of A cannot read B → 403
  - cross-school context → 403
Audit ownership denials (SEC_IDOR_BLOCKED / portal ownership)
```

## Scope (OUT)

```text
Admin scope-link API · Ranking · PDF · Phase 8 · new tables
```
