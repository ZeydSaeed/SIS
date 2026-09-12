# PHASE 7.9 — U01
# IMPLEMENTATION AUDIT + CLOSURE

---

```text
Unit: 7.9-U01 — Admin portal scope link/unlink/list HTTP
AuthZ: 04 GRANTED («استمر»)
Audit: PASS
Closure: CLOSED / ACCEPTED
Date: 2026-09-12
```

## Delivered

```text
Permission: portal.scopes.manage (+ portal_scopes_manager)
Commands: LinkPortalPartyScope / UnlinkPortalPartyScope
Query: ListPortalPartyScopes
Routes:
  POST   /api/v1/portal/scopes
  DELETE /api/v1/portal/scopes
  GET    /api/v1/portal/scopes?user_id=
School rules: student enrollment in context school;
  guardian → student_guardians → enrollment in context school
Audit: PrivilegeChanged
```

## Validation

```text
Phase79PortalScopesAdminHttpApiPostgreSqlTest → 6 passed / 19 assertions
architecture:validate --fitness → PASS
security:validate → PASS
```

```text
7.9-U01: CLOSED / ACCEPTED
```
