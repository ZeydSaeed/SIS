# OWASP Mapping — SIS Security Baseline

| OWASP Top 10 | SIS Rules | Controls |
|--------------|-----------|----------|
| A01 Broken Access Control | SEC-AUTHZ-001, SEC-AUTHZ-003 | Policies, negative tests |
| A02 Cryptographic Failures | SEC-AUTH-002, SEC-SECRET-001 | Hashed passwords, secret scan |
| A03 Injection | SEC-DB-001, SEC-INPUT-001 | Static analyzer, FormRequest |
| A04 Insecure Design | SEC-INTEL-001 | Intelligence boundary |
| A05 Security Misconfiguration | SEC-PROD-001, SEC-HEADER-001 | Config validation, headers |
| A06 Vulnerable Components | SEC-DEP-001 | composer audit |
| A07 Auth Failures | SEC-AUTH-001, SEC-RATE-001 | Sanctum, rate limits |
| A08 Software/Data Integrity | SEC-CI-001 | CI security gate |
| A09 Logging Failures | SEC-AUDIT-001, SEC-LOG-001 | Audit logger, log policy |
| A10 SSRF | N/A current slice | Future module review |

See `.cursor/security/SECURITY-BASELINE.json` for machine-readable rule IDs.
