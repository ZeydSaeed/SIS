# MASTER PHASE 7 — PHASE 7.9
# READINESS DISCOVERY

---

```text
Date: 2026-09-12
Mode: READ-ONLY
Predecessor: Phase 7.8 CLOSED WITH CONDITIONS (admin link deferred)
```

## Live

| Surface | Status |
|---------|--------|
| `security.scopes` | LIVE (UNIQUE user_id, scope_type, scope_id) |
| PortalPartyAccessService | LIVE (student\|guardian) |
| Portal results HTTP | LIVE |
| Admin link/unlink HTTP | **ABSENT** |
| `portal.scopes.manage` | **ABSENT** |

## Gap

```text
Portal ownership works only if scopes are inserted out-of-band (tests).
Production needs staff/admin HTTP to link/unlink with school-tenant checks.
```
