# Security Rules — SIS

## P0 — BLOCK

- Unauthenticated access to protected API resources
- Authorization bypass (`authorize(): true` without exception)
- Raw user input in SQL
- Secrets in source control
- Production autonomous execution enabled
- Intelligence modifying security policy or permissions

## P1 — BLOCK MERGE

- Missing rate limits on classified API routes
- PII exposure without permission
- Critical dependency vulnerabilities (composer audit)
- RBAC bypass patterns

## P2 — WARNING / TRACKED

- Missing security headers on non-critical paths
- Incomplete audit coverage for new modules

## Enforcement Matrix

See `docs/security/PHASE-3.10-GATE-REPORT.md` § Automatic Enforcement Matrix.

Critical rules MUST have: Static + Runtime + Test + CI — not Cursor-only.
