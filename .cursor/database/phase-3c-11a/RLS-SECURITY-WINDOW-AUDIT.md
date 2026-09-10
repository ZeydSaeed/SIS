# PHASE 3C.11A — RLS SECURITY WINDOW AUDIT

**CRITICAL — Gate C**

## Question

```text
Can any application request access Graduation tables before RLS is enforced?
```

## Planned sequence (from 3C.11)

```text
M01–M15 CREATE tables (no RLS)
M16 indexes
M17 ENABLE+FORCE RLS + policies
M18 triggers
→ Application deploy (feature flagged off)
```

## Analysis

| Factor | Assessment |
|--------|------------|
| Laravel routes/models today | None for Graduation — **current** exposure low |
| After M15, before M17 | Tables exist; default privileges depend on role that created them |
| FORCE RLS absent until M17 | Table owner and superuser paths may see all rows; app role with GRANT sees all schools |
| Feature flag | Planned **after** M17 — does not protect the M15→M17 interval |
| Empty tables | No academic data yet — reduces blast radius but **does not** satisfy “tenant protection required before access” if any GRANT exists |
| M17 failure | Leaves unprotected tables indefinitely |
| Concurrent engineer | Could merge Eloquent models against unprotected tables |

## Proof from plan?

| Claim | Proven? |
|-------|---------|
| Old app ignores unknown tables | **Partial** — true for current code |
| No access until RLS | **Not proven** — no REVOKE, no same-migration RLS, no CI gate forbidding Graduation app before M17 verify |
| Feature flag | **Insufficient** — applies too late |

```text
BLOCKING FINDING: F-11A-001
```

## Required remediation (do not implement here)

Implementers / plan amenders must adopt **one**:

1. **Option A — Preferred:** RLS ENABLE + FORCE + fail-closed policy in the **same** migration that creates each school-scoped table.  
2. **Option B:** After CREATE, `REVOKE ALL ON TABLE ... FROM PUBLIC, <app_role>`; GRANT only after M17 policies exist.  
3. **Option C:** Deploy pipeline atomicity + hard fail; zero Graduation application artifacts until RLS verification job passes.

## Fail-closed policy design (when applied)

| Condition | Expected |
|-----------|----------|
| `app.current_school_id` NULL / '' / missing | DENY |
| Invalid non-matching school | DENY |
| Cross-school | DENY |
| Matching school | ALLOW per policy |

Laravel authorization alone: **REJECTED** as sole control — plan correctly separates Policy vs RLS.
