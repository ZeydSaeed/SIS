# 08 — Audit Strategy

**Status:** Phase 1 design  
**Related:** ADR-013 outbox, ADR-014 idempotency, security_audit_logs

---

## Layers

| Layer | Store | Purpose |
|-------|-------|---------|
| Security audit | `security.security_audit_logs` | Authz/authn/security events |
| Domain audit | `audit.audit_logs` (**BP — not migrated**) | Entity change trail |
| Transactional outbox | `audit.outbox_messages` | Reliable domain events |
| Idempotency | `audit.idempotency_keys` | Safe retries |
| Intelligence | `intelligence.optimization_events`, `self_healing_actions` | Optimization trail (not SIS truth) |

---

## Must-audit actions (minimum)

- Student identity/PII changes  
- Enrollment create/cancel/placement  
- Grade create/correct  
- Attendance corrections  
- Financial postings/payments  
- Permission/role changes  
- Medical / SEN access and mutations  
- HR/payroll changes  
- Inventory stock mutations  
- RLS/policy/security configuration changes (manual ops)

---

## Immutability

Audit rows are not casually deleted or updated. Corrections append new events.

---

## Correlation

Every request should carry a correlation ID into audit/outbox metadata for tracing.

---

## Phase 1 note

No new audit tables in this phase. Creating `audit.audit_logs` (partitioned) is a later gated phase and must not replace `security_audit_logs`.
