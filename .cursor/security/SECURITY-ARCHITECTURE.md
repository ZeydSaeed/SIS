# SIS Security Architecture

## Principle

```text
NO TRUST BY DEFAULT
NO ACCESS WITHOUT AUTHORIZATION
NO INPUT WITHOUT VALIDATION
NO SECURITY BYPASS WITHOUT EXPIRING EXCEPTION
Security Policy > Intelligence Recommendation > Autonomous Action
```

## Enforcement Stack

```text
Developer
  → .cursor/rules/security.mdc (guidance)
  → SecurityArchitectureValidator (static)
  → ArchitectureValidator (integrated)
  → Security Tests (PHPUnit)
  → CI Security Gate (composer test → security:validate)
  → Runtime Middleware (auth, headers, rate limit, school context)
  → Policies + AuthorizationService (deny-by-default)
  → PostgreSQL (RLS where enabled, constraints)
  → SecurityAuditLogger + Security Events
  → Human Review (no destructive autonomous response)
```

## Layers

| Layer | Responsibility |
|-------|----------------|
| Presentation | Auth middleware, rate limits, headers, controller authorize() |
| Application | Handlers — no auth bypass, explicit commands |
| Domain | Pure — no security side effects |
| Infrastructure | Repositories scoped by tenant where applicable |
| Security | Policies, AuthorizationService, validators, audit |

## SSOT

Machine-readable baseline: `.cursor/security/SECURITY-BASELINE.json`  
Exceptions: `.cursor/security/SECURITY-EXCEPTIONS.yaml`  
Test matrix: `.cursor/security/SECURITY-TEST-MATRIX.yaml`

## Universal Pipeline

Every request/operation flows through:

Input → Correlation ID → Authentication → Authorization → Scope → Validation → Business Logic → Data Access → Output → Audit → Telemetry

## Intelligence Boundary

Intelligence may recommend only. It cannot grant permissions, modify authorization, or disable security controls.
