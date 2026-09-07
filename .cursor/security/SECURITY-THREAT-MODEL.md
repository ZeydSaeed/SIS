# Security Threat Model — SIS Phase 3.10

## Assets

- Student PII (national ID, demographics)
- User credentials and sessions
- Academic records
- Audit and intelligence data

## Threat Actors

- Unauthenticated external attacker
- Authenticated user (IDOR/BOLA)
- Privilege escalation via mass assignment
- Insider / admin abuse
- Intelligence layer misuse (recommendation as authorization)

## Top Threats

| ID | Threat | Mitigation | Rule |
|----|--------|------------|------|
| T-01 | Public API data exposure | Sanctum auth + policies | SEC-AUTH-001 |
| T-02 | IDOR on student records | Policy + scope | SEC-AUTHZ-001 |
| T-03 | SQL injection | Parameter binding, static scan | SEC-DB-001 |
| T-04 | Secret leakage | Secret scanner CI | SEC-SECRET-001 |
| T-05 | Production autonomous bypass | AutonomousExecutionPolicy | SEC-INTEL-002 |
| T-06 | PII over-exposure | Permission-gated sanitizer | SEC-AUTHZ-003 |
| T-07 | Brute force login | Fortify throttle + rate limits | SEC-RATE-001 |
| T-08 | CSRF on web | Laravel CSRF middleware | SEC-CSRF-001 |

## Out of Scope (Phase 3.10)

- Full RLS on all tables
- MFA mandate for all admins
- WAF / external penetration test
