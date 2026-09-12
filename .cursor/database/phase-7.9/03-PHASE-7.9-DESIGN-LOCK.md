# MASTER PHASE 7 — PHASE 7.9
# DESIGN LOCK

---

```text
Status: LOCKED
Date: 2026-09-12
Ballot: 02 APPLIED
```

## In

```text
- portal.scopes.manage / portal_scopes_manager
- CQRS: LinkPortalPartyScope / UnlinkPortalPartyScope / ListPortalPartyScopes
- HTTP:
  POST   /api/v1/portal/scopes
  DELETE /api/v1/portal/scopes
  GET    /api/v1/portal/scopes?user_id=
- scope_type ∈ {student, guardian}
- School-tenant validation per ballot
- PG tests + fitness/security
```

## Out

```text
- New tables / soft-delete columns
- Self-service user linking
- Phase 8
- Ranking / PDF
```

## Invariants

| ID | Rule |
|----|------|
| INV-79-01 | No new blueprint tables |
| INV-79-02 | Student link requires enrollment in context school |
| INV-79-03 | Guardian link requires student_guardians→enrollment in context school |
| INV-79-04 | portal.scopes.manage required (not results.view / portal.results.view alone) |
| INV-79-05 | Phase 8 remains HOLD |

## Units

| Unit | Name | Status |
|------|------|--------|
| 7.9-U01 | Link/Unlink/List HTTP + Application | CLOSED |
| 7.9-U02 | Final Closure Gate | CLOSED |
