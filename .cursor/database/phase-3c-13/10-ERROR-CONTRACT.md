# Phase 3C.13 — Error Contract

Application-level semantics first (HTTP mapping deferred to Interface phase).

| Situation | Application outcome | Retry? | Notes |
|-----------|---------------------|--------|-------|
| Duplicate idempotency key + same fingerprint | `IdempotentReplay` / Result.fromIdempotency | No (success path) | Not an error |
| Same key + different fingerprint | `IdempotencyPayloadConflict` | No | Existing domain exception |
| Business uniqueness violation (`23505`) | `GraduationBusinessConflict` / AlreadyExists | No insert-retry | DB final |
| Authorization failure | `AuthorizationDenied` | No | Fail closed |
| Tenant mismatch / GUC missing | `TenantContextViolation` | No | Fail closed |
| Stale / non-current version | `StaleGraduationVersion` | No (client refresh) | |
| Already approved | `ApprovalAlreadyDecided` | No | |
| Already revoked | `AwardAlreadyRevoked` | No | |
| Transaction conflict `40001` | `TransientConcurrencyFailure` | Yes bounded | |
| Deadlock `40P01` | `TransientConcurrencyFailure` | Yes bounded | |
| Unexpected DB failure | `PersistenceFailure` | Case-by-case | Log correlation_id |

## Mapping rules

1. Domain exceptions stay framework-free.  
2. Handlers do not catch-all and return false — fail loudly with typed outcomes.  
3. Controllers later map types → HTTP (409 conflict, 403 authz, 404, 422, 503 transient).  
4. Never convert fingerprint conflict into silent success.

## Verdict

```text
ERROR CONTRACT: PASS
```
