# Security Boundaries

## Trust Boundaries

| Boundary | Trust Level | Controls |
|----------|-------------|----------|
| HTTP/API ingress | UNTRUSTED | Auth, validation, rate limit |
| Queue payloads | UNTRUSTED | Minimal payload, re-auth in job |
| CLI | UNTRUSTED | Env guard, confirmation, audit |
| Import files | UNTRUSTED | Preflight, schema validation |
| Intelligence recommendations | UNTRUSTED | Human gate, no auto-privilege |
| PostgreSQL (app user) | TRUSTED storage | Least privilege, RLS where scoped |
| Redis | TRUSTED cache only | No secrets, network isolated |

## Tenant Scope

- Academic operations scoped to `academic_year_id`
- School context via `SchoolContextMiddleware` + RLS session var
- API authenticated requests include school context when available

## Module Boundaries

Security logic lives in `App\Security\` — not scattered in controllers.

Policies gate resource access. Handlers remain authorization-agnostic; controllers enforce policy before dispatch.
