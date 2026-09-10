# Phase 3C.13 — RLS Application Context Design

**No RLS changes. No roles created.** Design for future verification + safe runtime.

## Current mechanism (verified in code)

| Piece | Behavior |
|-------|----------|
| `SchoolContextResolver` | Resolves school from header/session/single-school |
| `SchoolContextMiddleware` | `set_config('app.current_school_id', $id, false)` |
| `terminate()` | Clears PHP context **and** sets GUC to `''` | 
| Graduation policies | Fail-closed: GUC null/empty → no rows; WITH CHECK school match |
| FORCE RLS | ON for all 14 graduation tables (3C.12/12A/12B) |

## How school_id enters the transaction

```text
HTTP request
  → middleware sets GUC on connection
  → Policy authorizes actor for school
  → Command.schoolId must equal context school
  → Handler asserts equality before writes
  → INSERT rows carry school_id (DB CHECK via RLS WITH CHECK + composite FKs)
```

## Connection pooling / leak risk

| Risk | Mitigation (design) |
|------|---------------------|
| Pooled connection retains prior GUC | Middleware `terminate` clears GUC (already implemented for HTTP) |
| Queue/job uses connection without GUC | **Gap:** no job-side set_config today — Graduation async writes MUST set+clear GUC per job |
| Long-lived artisan process | Explicit set/clear around each unit of work |

## Non-superuser RLS verification (3C.12B condition)

3C.12B ran as `postgres` (bypasses RLS). This is a **verification gap**, not proof RLS is broken.

### Future test design (do not implement now)

1. Create disposable role `sis_app_test` **NOSUPERUSER**, **NOBYPASSRLS**, GRANT DML on graduation (+ usage).  
2. Configure a PHPUnit connection alternate using that role against `sis_test` only.  
3. With GUC unset → SELECT/INSERT return 0 / fail closed.  
4. With GUC=school A → cannot read/write school B rows.  
5. Concurrent sessions A/B with different GUCs — no cross-school mutation.  
6. Never run this role against LIVE `sis` in destructive tests.

Requires **human approval** to create roles / grants (ops change).

## Application vs RLS

```text
Authentication → Authorization → Tenant Context → Business Authorization → Database RLS
```

Application filters are **not** a substitute for RLS.  
Design must not disable FORCE RLS or use security definer bypasses for Graduation writes.

## Verdict

```text
TENANT / RLS CONTEXT: PASS
```

Condition: queue GUC discipline + optional non-superuser proof role deferred to authorized verification phase.
