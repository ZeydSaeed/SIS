# PHASE 7.8 — U01 HUMAN IMPLEMENTATION AUTHORIZATION

---

```text
Date: 2026-09-12
Human: «استمر بما هو الافضل» + path A (portal)
Selected: 7.8-U01 Portal ownership + official portal HTTP readers
Rejected for now: Ranking portal · PDF · Phase 8 · admin scope-link API
Status: GRANTED
```

## Scope (IN)

```text
Permission + role: portal.results.view / portal_results_viewer
PortalPartyAccessService (scopes student|guardian + student_guardians)
GET /api/v1/portal/results/term
GET /api/v1/portal/results/annual
GET /api/v1/portal/results/gpa
GET /api/v1/portal/results/transcripts/issued
Reuse GetOfficial* / GetIssuedTranscript handlers
Ownership deny → 403 (not 404 enumeration preferred when unauthorized party)
PG feature tests
```

## Scope (OUT)

```text
Ranking · PDF · operational reads · admin link HTTP · Phase 8
New tables / user_id columns
```
