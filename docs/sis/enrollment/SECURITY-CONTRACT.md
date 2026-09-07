# Enrollment Module — Security Contract

**Version:** 1.0  
**Date:** 2026-09-07  
**Predecessor baseline:** Phase 3.10.1 — APPROVED  
**Scope:** Mandatory Definition of Done for Enrollment module gate

This contract extends Phase 3.10.1 patterns. It does **not** reopen Phase 3.10.1 unless a direct regression is proven.

---

## Contract Rules

Each rule requires **executable evidence** (test, policy, middleware, DB constraint, or static gate).  
"Looks secure" is not evidence.

---

## SC-01 — Authentication

**Rule:** Every protected enrollment operation requires authenticated identity.

| Operation | Requirement |
|-----------|-------------|
| View enrollment | `auth:sanctum` or session auth |
| Create enrollment | Authenticated |
| Update / cancel / transfer | Authenticated |
| Export / bulk | Authenticated |

**Current status:** **NOT APPLICABLE** — no HTTP routes exist.  
**Required for gate:** API routes behind `auth:sanctum`.

**Evidence target:** Feature test → unauthenticated request → 401.

---

## SC-02 — Authorization

**Rule:** Authentication alone is insufficient. Each sensitive operation requires explicit permission.

| Permission (proposed) | Operation |
|-----------------------|-----------|
| `enrollment.view` | List / show |
| `enrollment.create` | Enroll student |
| `enrollment.update` | Modify placement |
| `enrollment.cancel` | End enrollment |
| `enrollment.transfer` | Initiate transfer |
| `enrollment.export` | Export data |
| `enrollment.bulk` | Bulk operations |

**Current status:** **FAIL** — no permissions in `config/security.php`, no `EnrollmentPolicy`.

**Evidence target:** Policy tests + negative tests for unauthorized users → 403.

---

## SC-03 — Tenant Isolation

**Rule:** User cannot READ/CREATE/UPDATE/DELETE/EXPORT enrollment for another school.

**Required stack (match Students pattern):**

```text
Authentication
  → Permission
  → School Context (X-School-Id)
  → Resource school ownership
  → Authorization
  → Allow
```

**Current status:**

| Layer | Status |
|-------|--------|
| PostgreSQL RLS on `enrollment.enrollments` | **PASS** (fail-closed, Phase 3.10.1) |
| Application school scope on handler | **FAIL** — handler trusts command.schoolId |
| Repository queries scoped by school | **FAIL** — no read queries |
| RLS on classes/sections/subjects | **FAIL** — not enabled |

**Evidence target:** Cross-school tests → 403 (or 404 per project policy).

---

## SC-04 — IDOR / BOLA Protection

**Rule:** Direct ID access to another school's enrollment must fail.

```http
GET /api/v1/enrollments/{id}
```

**Expected:** 403 or 404 (consistent with Students module).

**Current status:** **NOT TESTED** — no API.

---

## SC-05 — Mass Assignment

**Rule:** Client payloads must not control security-sensitive fields unless explicitly authorized workflow.

**Protected fields:**

```text
school_id
enrolled_by
created_by
approved_by
status
effective_to
tenant_id
role / permissions
```

**Current status:**

| Area | Status |
|------|--------|
| `EnrollmentRecord::$fillable` includes `school_id`, `enrolled_by`, `status` | **RISK** — differs from Student pattern (server-side forceFill) |
| FormRequest guards | **N/A** — no requests |
| `EnrollStudentCommand.enrolledBy` | Client-trusted if wired from HTTP without server override |

**Evidence target:** Mass assignment tests → 422 on prohibited fields.

---

## SC-06 — State Transition Security

**Rule:** Users cannot skip workflow (e.g. Pending → Active without Approved).

**Current status:** **N/A** — only create-to-active exists; no workflow states in code.

**Required when implemented:** Domain rules + policy + tests for forbidden transitions.

---

## SC-07 — Input Validation

**Rule:** Validate required fields, invalid IDs, duplicate enrollment, cross-school references.

**Current status (handler only):**

| Check | Implemented |
|-------|-------------|
| Student exists | Yes |
| Student active | Yes |
| Duplicate active enrollment per year | Yes |
| Class/section belong to school | **No** |
| Student belongs to school | **No** |
| Academic year valid for school | **No** |
| Invalid dates | Partial (effectiveFrom string, no validator) |

**Evidence target:** FormRequest rules + feature tests.

---

## SC-08 — SQL / ORM Safety

**Rule:** No unsafe raw SQL; parameterized queries; no dynamic column injection.

**Current status:** **PASS** — Eloquent/query builder only in enrollment code; no raw concatenation found.

**Evidence:** Static review + existing `SecurityStaticAnalyzer` in CI.

---

## SC-09 — Auditability

**Rule:** Sensitive operations traceable: Who, What, When, Target, School, result.

**Current status:**

| Mechanism | Status |
|-----------|--------|
| `RecordStudentEnrolledAudit` | **FAIL** — `Log::info` only |
| `SecurityAuditLogger` → `security_audit_logs` | **Not wired** for enrollment |
| `enrolled_by` from trusted actor | **FAIL** — command field |

**Evidence target:** SecurityAuditPersistence-style tests for enroll + deny events.

---

## SC-10 — Bulk Operations

**Rule:** Bulk update/delete/approve/transfer/export must re-apply full security stack.

**Current status:** **N/A** — no bulk operations.

**Required when implemented:** Same middleware + policy + school scope per row/batch.

---

## Contract Gate Threshold

Enrollment module gate **must not pass** if:

```text
Critical Security Finding > 0
High Security Finding > 0 (without documented approval)
```

---

## Mapping to Phase 3.10.1 Controls

| Phase 3.10.1 control | Enrollment application parity |
|----------------------|-------------------------------|
| School-scoped IDOR/BOLA | Required — **missing** |
| Mandatory school context | Required on API — **missing** |
| RLS fail-closed | DB layer **pass** for enrollments table |
| Structured security audit | Required — **missing** |
| Mass assignment protection | Required — **partial risk** |
| Composer audit CI | Inherited from project — **pass** |
