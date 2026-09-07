# Phase 3.10 — Security Before State

**Date:** 2026-09-07  
**Scope:** Read-only discovery prior to Phase 3.10 implementation  
**Status:** BASELINE CAPTURED — do not treat as post-fix state

---

## Executive Summary

SIS has strong **architecture** and **intelligence governance** enforcement, but **application security** is partially implemented. Web authentication (Fortify + 2FA + passkeys) exists; **API routes are unauthenticated**; **RBAC schema exists without application wiring**; **policies are absent**; form requests use `authorize(): true`. Intelligence production autonomous execution is correctly blocked.

**Pre-implementation maturity:** Level 1 (Basic) — trending toward Level 2 with schema foundations.

---

## Current Authentication

| Component | State |
|-----------|-------|
| Fortify | ✅ Login, registration, 2FA, passkeys |
| Session guard | ✅ `web` guard, session driver |
| API guard | ❌ No Sanctum/token guard on `/api/v1/*` (pre-fix) |
| Password hashing | ✅ `hashed` cast on User |
| Password policy | ✅ Production: min 12, mixed case, uncompromised (AppServiceProvider) |
| Session regeneration | ✅ Fortify default |
| MFA | ✅ TwoFactorAuthenticatable + Passkeys |
| Token revocation | ❌ No API tokens configured (pre-fix) |

---

## Current Authorization

| Component | State |
|-----------|-------|
| RBAC schema | ✅ `security.roles`, `permissions`, `role_permissions`, `user_roles`, `scopes` |
| Application wiring | ❌ No Gate/Policy registration, no permission service |
| Policies | ❌ None for Student or other resources |
| Deny-by-default | ❌ Not enforced — `authorize(): true` on form requests |
| `if ($user->isAdmin)` | ❌ Not present (good) but also no permission checks |

---

## Current Roles & Permissions

- **Database:** Tables created via `2026_09_05_100300_create_security_tables.php`
- **Seed data:** No security permissions/roles seeder wired to tests or foundation seed
- **Runtime:** No code reads `security.user_roles` or `security.role_permissions`

---

## Current Policies

- **Laravel Policies:** None registered
- **Student API:** No `$this->authorize()` in controller or meaningful form request authorization

---

## Current API Security

| Route | Auth (pre-fix) | Authorization | Rate limit |
|-------|----------------|---------------|------------|
| `GET /api/v1/health` | Public (intentional) | N/A | Default |
| `GET/POST/PUT /api/v1/students*` | **None — P0** | **None — P0** | Default only |
| Correlation ID | ✅ API prepend middleware | — | — |
| Telemetry | ✅ RequestTelemetryMiddleware | — | — |
| Error sanitization | ✅ JSON domain exceptions | — | — |
| PII in responses | ⚠️ `national_id` exposed in StudentDetailDTO | — | — |

---

## Current Middleware

**API stack (`bootstrap/app.php`):**
- `CorrelationIdMiddleware` (prepend)
- `RequestTelemetryMiddleware` (append)

**Web stack:**
- `CorrelationIdMiddleware`
- `SchoolContextMiddleware` (RLS session var)
- Inertia / appearance middleware

**Missing (pre-fix):**
- API authentication middleware
- Security headers middleware
- API-specific rate limiting
- SchoolContext on authenticated API requests

---

## Current Validation

- Student create/update: FormRequest with field rules ✅
- Authorization in FormRequest: `return true` ❌
- No mass-assignment via `request()->all()` in handlers ✅ (explicit validated fields)

---

## Current Session Configuration

- Laravel default session driver (env-driven)
- Cookie encryption enabled (except appearance/sidebar_state)
- CSRF: Laravel default for web routes
- API routes: stateless JSON (no CSRF — requires token/session auth instead)

---

## Current CSRF

- Web mutations: Laravel CSRF middleware (default)
- API: No CSRF (expected for token API once auth added)
- No global CSRF disable detected

---

## Current Rate Limiting

- Fortify login throttling: via FortifyServiceProvider (if configured)
- API: No dedicated `RateLimiter::for('api')` (pre-fix)
- Bulk/search/upload limits: Not classified

---

## Current Database Security

| Control | State |
|---------|-------|
| Migrations versioned | ✅ |
| FK / constraints | ✅ Foundation migrations |
| RLS | ⚠️ 2 tables (`enrollment`, `attendance`) — NULL context bypass when unset |
| App DB user least privilege | ⚠️ Not verified in CI |
| Raw SQL in app | Limited — SchemaGuardian, optimization paths use allowlists |
| `DB::prohibitDestructiveCommands` | ✅ Production |

---

## Current Secrets Handling

- `.env` gitignored ✅
- No secret scanner in CI (pre-fix)
- No hardcoded production secrets found in discovery scan of app code

---

## Current Logging

- Laravel default logging
- Correlation ID on requests ✅
- No dedicated log sanitization policy file (pre-fix)
- Password/token logging: not detected in app code

---

## Current Audit

- Intelligence audit tables exist for optimization
- No unified Security Audit log for login/permission/data access (pre-fix)

---

## Current CI Security

**`composer ci:check` (pre-fix):**
- Pint, PHPStan, `architecture:validate --fitness`, PHPUnit
- No `security:validate`
- No `composer audit` gate
- No secret scan

---

## Current Docker Security

- Sail available in dev dependencies
- No Docker security baseline scan in CI (pre-fix)

---

## Current Redis Security

- Cache/session via env configuration
- No Redis ACL verification in CI (pre-fix)

---

## Current PostgreSQL Security

- Primary workload target
- RLS partial (see above)
- SSL/TLS: environment-dependent

---

## Current File Upload Handling

- No general file upload endpoints in Students slice
- Not applicable yet — document for future modules

---

## Current Import Handling

- SQL import path noted in architecture audit as ungoverned
- CSV/Excel bulk import: not implemented in Students slice

---

## Intelligence Security Boundary

| Control | State |
|---------|-------|
| Production autonomous block | ✅ `AutonomousExecutionPolicy::isProductionAutonomousBlocked()` |
| Intelligence cannot grant permissions | ✅ No such code path |
| Analyze allowlist | ✅ Strict target validation |
| Kill switch | ✅ Present in optimization config |
| Recommendation ≠ Authorization | ✅ Enforced by design |

---

## Current Autonomous Paths

- Self-healing / ANALYZE: Tier 1 with allowlist, production block, audit
- No autonomous security mutation paths detected

---

## Security Gaps (Pre-Fix)

| ID | Severity | Finding |
|----|----------|---------|
| S-01 | P0 | Student API fully public — no authentication |
| S-02 | P0 | No authorization policies — deny-by-default not enforced |
| S-03 | P0 | Form requests `authorize(): true` |
| S-04 | P1 | RBAC schema unused at runtime |
| S-05 | P1 | `national_id` exposed in API responses without permission gate |
| S-06 | P1 | SchoolContextMiddleware not on API stack |
| S-07 | P1 | RLS NULL bypass when school context unset |
| S-08 | P2 | No security headers middleware |
| S-09 | P2 | No API rate limit classification |
| S-10 | P2 | No security CI gate (SAST, secrets, audit) |
| S-11 | P2 | No SecurityArchitectureValidator |
| S-12 | P2 | No security audit event taxonomy wired |
| S-13 | P3 | No MFA mandate for privileged users (readiness only) |

---

## Existing Exceptions

- None documented in `SECURITY-EXCEPTIONS.yaml` (file did not exist pre-fix)
- Legacy `app/Services` allowed with `@architecture-legacy-allowed` marker

---

## Existing Legacy Code

| Path | Classification |
|------|----------------|
| `app/Services/` | Approved legacy — no new business logic |
| Controllers with handlers | ✅ Clean Architecture compliant for Students |

---

## Discovery Evidence

- `routes/api.php` — no auth middleware on student routes
- `bootstrap/app.php` — API lacks SchoolContext + auth
- `app/Http/Requests/Student/*` — `authorize(): true`
- `app/Architecture/SecurityFitnessChecker.php` — SEC-001 to SEC-004 only (middleware/RLS presence)
- `database/migrations/2026_09_05_100300_create_security_tables.php` — RBAC schema only

---

## Next Step

Phase 3.10 implementation addresses P0/P1 gaps via Security SSOT, SecurityArchitectureValidator, API auth + policies, security tests, and CI gate — without enabling production autonomous execution or intelligence security bypass.
