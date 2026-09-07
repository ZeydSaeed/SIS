# Security Controls Catalog

| Control | Type | Implementation | Status |
|---------|------|----------------|--------|
| API Authentication | Preventive | Sanctum + auth middleware | Phase 3.10 |
| Deny-by-default Authorization | Preventive | StudentPolicy + DatabaseAuthorizationService | Phase 3.10 |
| Input Validation | Preventive | FormRequest rules | Existing |
| Rate Limiting | Preventive | RateLimiter in bootstrap | Phase 3.10 |
| Security Headers | Preventive | SecurityHeadersMiddleware | Phase 3.10 |
| Static Analysis | Detective | SecurityArchitectureValidator | Phase 3.10 |
| Secret Scan | Detective | SecretScanner | Phase 3.10 |
| Security Audit Log | Detective | SecurityAuditLogger | Phase 3.10 |
| Correlation ID | Detective | CorrelationIdMiddleware | Existing |
| Production Autonomous Block | Preventive | AutonomousExecutionPolicy | Existing |
| Intelligence Governance | Preventive | IntelligenceGovernanceChecker | Existing |
| RLS (partial) | Preventive | PostgreSQL policies | Existing |
| Dependency Audit | Detective | composer audit in security:validate | Phase 3.10 |

## Response Model

Detect → Audit → Alert → Human Review (no autonomous destructive response in Phase 3.10)
