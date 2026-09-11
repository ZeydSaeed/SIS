# PHASE 4.1A — CERTIFICATES SCHEMA DESIGN LOCK

**Date:** 2026-09-11  
**Mode:** SCHEMA DESIGN ONLY — NO DDL / NO MIGRATION  
**Authorization:** Human — Phase 4.1A Schema Design Lock only  
**Predecessors:**  
- `.cursor/database/phase-4/0B-CERTIFICATES-DESIGN-LOCK.md` (PASS WITH CONDITIONS)  
- `.cursor/database/phase-4/PHASE-4-READINESS-AND-SCOPE-LOCK.md`

```text
PHASE 4.1A — CERTIFICATES SCHEMA DESIGN LOCK
DDL IMPLEMENTATION: NOT AUTHORIZED
NO MIGRATIONS · NO INDEXES CREATED · NO RLS DDL · NO CODE
NO GRADUATION / AWARD / ENROLLMENT / GRADES MUTATION
```

---

## 1. Scope / Authorization

This unit locks the **physical schema design** for Certificates under namespace `certificates`, implementing Phase 4.0B conceptual contracts.

**In scope:** table inventory, columns (conceptual), PK/FK/UNIQUE/CHECK, delete semantics, RLS requirements, index *design*, concurrency uniqueness.

**Out of scope:** executing DDL, PHP, HTTP, permissions, Graduation changes, CQRS, jobs.

---

## 2. Existing Schema Verification

Verified against LIVE migration `2026_09_10_170500_phase3c12_graduation_approvals_awards.php` (+ reject-delete `170900`, RLS via `GraduationTenantProtection`).

| Object | LIVE fact | Relevance |
|--------|-----------|-----------|
| `graduation_awards` | UNIQUE `(school_id, enrollment_id)` | One award head per enrollment |
| | `current_issued_version_id` FK → versions(id) RESTRICT | Pointer-only current read |
| | **No** UNIQUE `(id, school_id)` | Composite FK to awards via `(award_id, school_id)` **not** available without Graduation DDL |
| `graduation_award_versions` | UNIQUE `(graduation_award_id, version_no)` | Version sequence |
| | UNIQUE INDEX `(id, school_id)` | **Enables** composite FK `(version_id, school_id)` |
| | `school_id` NOT NULL | Tenant on version |
| `revocation_records` | Award-version scoped | Award revoke ≠ cert delete |
| RLS | ENABLE + FORCE on award tables | Pattern to mirror |
| Reject-delete | Awards + versions protected | Pattern for official cert history |
| `certificates` schema | Empty reserved | Target namespace |

**No contradiction** with Phase 4.0B pin to `graduation_award_version_id`.

---

## 3. Proposed Table Inventory

| Table | Purpose | Required Phase 4.1? | Why not defer |
|-------|---------|---------------------|---------------|
| `certificates.certificate_templates` | Template identity head | **Yes** | Issuance must pin immutable template revision |
| `certificates.certificate_template_versions` | Immutable template revision | **Yes** | Mutable template alone forbidden by 4.0B |
| `certificates.certificates` | Certificate identity (family per school+enrollment+type) | **Yes** | Identity ≠ issuance |
| `certificates.certificate_issuances` | Official issuance + Award version pin + verify ids | **Yes** | Core official record |
| `certificates.certificate_artifacts` | Artifact metadata (0..N per issuance) | **Yes** | Async generation produces artifacts without embedding BLOBs |
| `certificates.certificate_generation_jobs` | Durable async work | **Yes** | Intent + outbox consumer need durable job state |

**Rejected / not proposed:**

| Candidate | Decision |
|-----------|----------|
| Single giant `issued_certificates` (blueprint) | **Rejected** — conflates identity, issuance, artifact, job |
| `graduation_id` column | **Rejected** — stale |
| Separate `verification_identities` table | **Rejected** — verification belongs on issuance |
| Separate `certificate_reissues` table | **Rejected** — reissue = new issuance + supersession link |
| Copy of `audit.idempotency_keys` | **Rejected** — reuse audit infra |

---

## 4. Aggregate Ownership

| Aggregate | Root table | Children |
|-----------|------------|----------|
| CertificateTemplate | `certificate_templates` | `certificate_template_versions` |
| Certificate | `certificates` | `certificate_issuances` → `certificate_artifacts`, `certificate_generation_jobs` |

Graduation Award remains **external referenced aggregate** (not owned).

---

## 5. Certificate Identity

### Table: `certificates.certificates`

**Boundary (LOCKED):**

```text
UNIQUE (school_id, enrollment_id, certificate_type)
```

One **identity** per school + enrollment + certificate type family.

| Column (conceptual) | Null | Notes |
|---------------------|------|-------|
| `id` | NO | BIGINT IDENTITY PK |
| `school_id` | NO | Tenant |
| `enrollment_id` | NO | Academic context |
| `student_id` | NO | Denorm display only — not sole identity |
| `certificate_type` | NO | SMALLINT slot (values HDR — D-CERT-03) |
| `created_at` | NO | |
| `created_by` | YES | Opaque actor |

**Does not store:** award pin, certificate_number, verification_code, lifecycle of a specific document (those belong on **issuance**).

**FK:**

- `(enrollment_id, school_id)` → `enrollment.enrollments(id, school_id)` ON DELETE **RESTRICT**
- `school_id` → `organization.schools(id)` ON DELETE **RESTRICT**
- `student_id` → `students.students(id)` ON DELETE **RESTRICT**

---

## 6. Issuance Identity

### Table: `certificates.certificate_issuances`

**Cardinality (LOCKED):**

```text
Certificate 1 ── 0..N Issuances
```

| Column (conceptual) | Null | Notes |
|---------------------|------|-------|
| `id` | NO | BIGINT IDENTITY PK |
| `school_id` | NO | Tenant (must match certificate + award version) |
| `certificate_id` | NO | Parent identity |
| `issuance_no` | NO | INTEGER ≥ 1, monotonic per certificate |
| `graduation_award_version_id` | NO | **Authoritative Award pin** |
| `graduation_award_id` | NO | Denorm companion (4.0B) |
| `enrollment_id` | NO | Denorm companion (must match certificate.enrollment_id) |
| `template_version_id` | NO | Immutable template pin |
| `lifecycle_status` | NO | SMALLINT — see §15 |
| `certificate_number` | NO | Human-facing (on **issuance**) |
| `verification_code` | NO | Public opaque verify id (on **issuance**) |
| `supersedes_issuance_id` | YES | Prior issuance this replaces |
| `issued_at` | YES | Set when ISSUED |
| `issued_by` | YES | Opaque |
| `revoked_at` / `revoked_by` / `revoke_reason_ref` | YES | When REVOKED |
| `correlation_id` | YES | Trace |
| `created_at` | NO | Intent time |
| `created_by` | YES | |

**Uniqueness:**

```text
UNIQUE (certificate_id, issuance_no)
```

**Issuance sequence:** Application assigns `issuance_no = max(existing)+1` under row lock / within txn; DB UNIQUE is final guard. **Not** `MAX(id)` as business sequence authority — `issuance_no` is.

---

## 7. Reissue / Supersession Model

| Concept | Schema representation |
|---------|----------------------|
| Original | `issuance_no = 1`, `supersedes_issuance_id IS NULL` |
| Replacement / reissue | New row; `supersedes_issuance_id` → prior issuance; prior → `SUPERSEDED` |
| Correction | Same mechanism as reissue — legal label **HDR** (D-CERT-05); no separate table |
| Revoked | `lifecycle_status = REVOKED`; row retained |
| Superseded | `lifecycle_status = SUPERSEDED`; row retained |

### Self-reference (LOCKED)

```text
supersedes_issuance_id
  → certificate_issuances(id)
  ON DELETE RESTRICT
  NULLABLE
```

**Rules:**

| Rule | Contract |
|------|----------|
| Same school | Enforced via app + both rows carry `school_id`; prefer also UNIQUE `(id, school_id)` on issuances to allow composite FK `(supersedes_issuance_id, school_id)` |
| Same certificate family | Application invariant: superseding issuance’s `certificate_id` equals prior’s `certificate_id` |
| Cycles | Application forbids; DB cannot cheaply forbid all cycles — document invariant |
| Lineage query | Walk `supersedes_issuance_id` / filter by `certificate_id` + `issuance_no ASC` — **not** MAX(created_at) |

**Forbidden lineage reconstruction:** `MAX(created_at)`, `MAX(id)`, “latest row” without `issuance_no` / supersession link.

Optional companion column `superseded_by_issuance_id` (nullable) — **DEFERRED** (symmetric pointer like Graduation). Minimum lock: **forward** `supersedes_issuance_id` only.

---

## 8. Award Version FK Model

### Authoritative FK (LOCKED)

```text
FOREIGN KEY (graduation_award_version_id, school_id)
  REFERENCES graduation.graduation_award_versions (id, school_id)
  ON DELETE RESTRICT
```

**Compatible** with LIVE `graduation_award_versions_id_school_uidx`.

This **prevents** cross-school pin:

```text
issuance.school_id = A  +  award_version from school B  → FK violation
```

### Additional `graduation_award_id`

```text
FOREIGN KEY (graduation_award_id)
  REFERENCES graduation.graduation_awards (id)
  ON DELETE RESTRICT
```

**Not** composite `(graduation_award_id, school_id)` in Phase 4.1 — parent lacks UNIQUE `(id, school_id)` and this lock **forbids** modifying Graduation solely to add it.

### Consistency invariants (write-time — Application + optional CHECK via trigger later)

```text
issuance.graduation_award_id
  = award_version.graduation_award_id

issuance.enrollment_id
  = certificates.enrollment_id
  = graduation_awards.enrollment_id (for that award_id)

issuance.school_id
  = certificates.school_id
  = award_version.school_id
```

Documented as **mandatory transaction validation**; DB enforces school match on version pin.

**ON DELETE CASCADE:** **Forbidden** on Award FKs.

---

## 9. School / Enrollment Source Context

| Field | Authoritative owner | Also on |
|-------|---------------------|---------|
| `school_id` | Every school-scoped table | Identity, issuance, artifact, job, templates |
| `enrollment_id` | `certificates` identity | **Denorm on issuance** for RLS/query without join — justified |
| `graduation_award_id` | Issuance (denorm) | Not on identity/artifact/job |
| `graduation_award_version_id` | **Issuance only** | Not duplicated on artifact/job (job links via issuance_id) |

**Justification for enrollment denorm on issuance:** listing/verify support queries and RLS school policies; SoT remains identity + award head; write path copies and validates equality.

**student_id:** on identity (denorm); optional copy on issuance **not required**.

---

## 10. Certificate Number Uniqueness (D-CERT-02)

### Decision (LOCKED)

```text
certificate_number lives on certificate_issuances
UNIQUE (school_id, certificate_number)
```

**Not** global unique — schools may use overlapping formats; tenant isolation is primary.

**Not** on certificate identity — reissue/replacement receives a **new** issuance row and typically a **new** number (document instance). If product later wants stable “family number,” that is a separate optional field (**deferred**).

**Active collision:** UNIQUE prevents two issuances in same school sharing a number regardless of lifecycle (including REVOKED/SUPERSEDED). Remains durable identifiers. If reuse-after-revoke is ever required, that needs explicit future redesign — **default: no reuse**.

---

## 11. Verification Code Uniqueness (D-CERT-02)

### Decision (LOCKED)

```text
verification_code lives on certificate_issuances
UNIQUE (verification_code)   -- GLOBAL
NOT NULL
```

**Rationale:** Public verify often has **no school context** in the identifier (ADR-003 / api sketch). Global unique yields unambiguous:

```text
verification_code → exactly one issuance
```

**Timing:** Assigned **inside IssueCertificate transaction** at PENDING_GENERATION (before async generate) so retries do not allocate a second code for the same idempotent command.

**Revoked/superseded:** **Retain** the same code; lookup returns status REVOKED/SUPERSEDED — do not null out.

**Generation strategy (conceptual):** cryptographically strong opaque string (length/format HDR at implementation). Not BIGINT id. Not sequential guessable.

---

## 12. Template + Template Version Model

### `certificate_templates`

| Column | Notes |
|--------|-------|
| `id` | PK |
| `school_id` | NOT NULL for school-owned; **HDR** if platform-shared later (D-CERT-04) — Phase 4.1: **school_id NOT NULL** |
| `certificate_type` | SMALLINT |
| `name` | |
| `status` | SMALLINT draft/active/retired (conceptual 1–3) |
| `created_at` / `created_by` | |

```text
UNIQUE (school_id, certificate_type, name)  -- or code column; exact natural key HDR soft
```

### `certificate_template_versions`

| Column | Notes |
|--------|-------|
| `id` | PK |
| `school_id` | NOT NULL (match template) |
| `template_id` | Parent |
| `version_no` | INTEGER ≥ 1 |
| `locale` | VARCHAR slot nullable/HDR |
| `content_hash` | NOT NULL — immutability fingerprint |
| `template_storage_key` | Layout/config reference (not live mutable editor state) |
| `effective_from` / `effective_to` | Nullable slots |
| `created_at` / `created_by` | |

```text
UNIQUE (template_id, version_no)
UNIQUE (id, school_id)  -- for composite FKs if needed
FK (template_id) → templates ON DELETE RESTRICT
FK (template_id, school_id) if templates get (id, school_id) unique — recommend UNIQUE (id, school_id) on templates
```

**Issuance pin:** `template_version_id` NOT NULL → template_versions(id) RESTRICT (prefer composite with school_id).

**Immutability:** Versions are insert-only for content; no UPDATE of `content_hash` / storage_key after create (enforce via trigger or app — trigger preferred at DDL unit).

Both tables **required** in Phase 4.1 — cannot pin issuance to mutable template alone.

---

## 13. Artifact Model

### `certificate_artifacts`

```text
1 issuance → 0..N artifacts
```

| Column | Notes |
|--------|-------|
| `id` | PK |
| `school_id` | NOT NULL |
| `issuance_id` | Parent |
| `attempt_no` | INTEGER ≥ 1 |
| `storage_key` | NOT NULL |
| `content_type` | NOT NULL |
| `file_hash` | NOT NULL VARCHAR(64) SHA-256 intent |
| `byte_size` | NOT NULL BIGINT ≥ 0 |
| `generated_at` | NOT NULL |
| `generator_version` | NOT NULL VARCHAR |
| `is_current` | BOOLEAN NOT NULL DEFAULT false |
| `created_at` | |

```text
UNIQUE (issuance_id, attempt_no)
PARTIAL UNIQUE (issuance_id) WHERE is_current  -- at most one current artifact
```

**No PDF/BLOB columns.**

Failed generation attempts may omit artifact rows (job FAILED only) or store failed attempt metadata on **job** — prefer job error fields; artifacts only for successful storage writes.

---

## 14. Generation Job Model

### `certificate_generation_jobs`

One durable job per generation request (typically 1:1 with issuance at first issue; retries update same job or append attempts — **LOCKED:** single job row per issuance with `attempt_count`).

| Column | Notes |
|--------|-------|
| `id` | PK |
| `school_id` | NOT NULL |
| `issuance_id` | NOT NULL |
| `job_status` | SMALLINT — pending/running/succeeded/failed |
| `attempt_count` | INTEGER NOT NULL DEFAULT 0 |
| `last_error_ref` | VARCHAR nullable opaque |
| `correlation_id` | |
| `queued_at` / `started_at` / `finished_at` | |
| `created_at` | |

```text
UNIQUE (issuance_id)  -- one job record per issuance for Phase 4.1 simplicity
```

**Not** a second idempotency store. Command idempotency remains `audit.idempotency_keys`. Job consumer must be idempotent on `issuance_id` + status.

Separate `generation_attempt` table: **deferred** — `attempt_count` + artifact `attempt_no` sufficient for Phase 4.1.

---

## 15. Status Model

### Convention: `SMALLINT` (SIS standard — not PG ENUM)

### Issuance `lifecycle_status` (LOCKED)

| Code | Name | Meaning |
|------|------|---------|
| 1 | PENDING_GENERATION | Intent persisted; artifact not ready |
| 2 | ISSUED | Official; artifact available |
| 3 | FAILED | Generation failed; not officially usable |
| 4 | REVOKED | Revoked; history retained |
| 5 | SUPERSEDED | Replaced by later issuance |

```text
CHECK (lifecycle_status BETWEEN 1 AND 5)
```

**Not included:** DRAFT, CANCELLED, ARCHIVED (4.0B).

### Job `job_status` (LOCKED)

| Code | Name |
|------|------|
| 1 | PENDING |
| 2 | RUNNING |
| 3 | SUCCEEDED |
| 4 | FAILED |

```text
CHECK (job_status BETWEEN 1 AND 4)
```

### Template `status`

| Code | Name |
|------|------|
| 1 | DRAFT |
| 2 | ACTIVE |
| 3 | RETIRED |

(Template draft ≠ certificate issuance draft.)

---

## 16. PK / FK Design (summary)

| Child | Parent | Columns | ON DELETE |
|-------|--------|---------|-----------|
| template_versions | templates | `template_id` (+ school composite if available) | **RESTRICT** |
| certificates | schools | `school_id` | **RESTRICT** |
| certificates | enrollments | `(enrollment_id, school_id)` | **RESTRICT** |
| certificates | students | `student_id` | **RESTRICT** |
| issuances | certificates | `certificate_id` (+ school composite recommended) | **RESTRICT** |
| issuances | award_versions | `(graduation_award_version_id, school_id)` | **RESTRICT** |
| issuances | awards | `graduation_award_id` | **RESTRICT** |
| issuances | template_versions | `template_version_id` (+ school) | **RESTRICT** |
| issuances | issuances | `supersedes_issuance_id` | **RESTRICT** |
| artifacts | issuances | `issuance_id` (+ school) | **RESTRICT** |
| jobs | issuances | `issuance_id` (+ school) | **RESTRICT** |

**SET NULL:** not used for official history FKs.  
**CASCADE:** **forbidden** on official history.

Recommend UNIQUE `(id, school_id)` on: templates, template_versions, certificates, issuances, artifacts, jobs — for composite school-safe FKs (Graduation pattern).

---

## 17. CHECK Constraints (conceptual)

```text
issuance_no >= 1
attempt_no >= 1
byte_size >= 0
lifecycle_status BETWEEN 1 AND 5
job_status BETWEEN 1 AND 4
template status BETWEEN 1 AND 3
certificate_type >= 1   -- exact range HDR when catalog locked
```

Optional deferred CHECKs: ISSUED ⇒ issued_at NOT NULL; REVOKED ⇒ revoked_at NOT NULL.

---

## 18. UNIQUE Constraints (locked set)

| Table | UNIQUE |
|-------|--------|
| certificates | `(school_id, enrollment_id, certificate_type)` |
| certificate_issuances | `(certificate_id, issuance_no)` |
| certificate_issuances | `(school_id, certificate_number)` |
| certificate_issuances | `(verification_code)` global |
| certificate_template_versions | `(template_id, version_no)` |
| certificate_artifacts | `(issuance_id, attempt_no)` |
| certificate_artifacts | partial `(issuance_id) WHERE is_current` |
| certificate_generation_jobs | `(issuance_id)` |
| + | `(id, school_id)` on each school-scoped table |

---

## 19. DELETE Semantics

| Table | Hard delete | Mechanism |
|-------|-------------|-----------|
| certificates | **REJECTED** | Reject-delete trigger at DDL unit |
| certificate_issuances | **REJECTED** | Reject-delete trigger |
| certificate_artifacts | **REJECTED** for official metadata | Reject-delete trigger |
| certificate_template_versions | **REJECTED** after any issuance pin | Reject-delete; retire template instead |
| certificate_templates | **REJECTED** if versions exist | RESTRICT + reject-delete |
| certificate_generation_jobs | **Prefer retain**; optional ops purge of terminal FAILED jobs **HDR** — default **REJECTED** in Phase 4.1 for simplicity |

```text
Official certificate history: DELETE = REJECTED
```

---

## 20. RLS Design

Every school-scoped Certificates table:

```text
ENABLE ROW LEVEL SECURITY
FORCE ROW LEVEL SECURITY
```

**Contract (no policy SQL yet):**

| Concern | Requirement |
|---------|-------------|
| Actor context | `app.current_school_id` (existing GUC pattern) |
| USING / WITH CHECK | `school_id = current_setting(...)::bigint` fail-closed |
| Cross-school | Deny |
| Generation worker | Must set school context to issuance.school_id; no BYPASSRLS in app role |
| Policy names | **Not invented here** — define at DDL with helper akin to `GraduationTenantProtection` |
| Graduation RLS | Unchanged |

Public verification (future HTTP) is an Application read under controlled service path — **not** an RLS weaken; design separately under security review.

---

## 21. Index Design (design only — DO NOT CREATE)

| Index | Shape | Query | Phase 4.1? |
|-------|-------|-------|------------|
| PK / UNIQUE above | — | Integrity | **Yes** (as constraints) |
| — | UNIQUE verification_code | Public verify | **Yes** (constraint) |
| — | UNIQUE (school_id, certificate_number) | Staff lookup | **Yes** |
| Supporting | `(school_id, enrollment_id)` on certificates | Already covered by identity UNIQUE prefix | Constraint suffices |
| Supporting | `(school_id, lifecycle_status)` on issuances | Ops queues | **Deferred** pending EXPLAIN |
| Supporting | `graduation_award_version_id` | Reverse audit | **Deferred** unless support query proven hot |
| Supporting | `(school_id, job_status)` on jobs | Worker poll | **Yes candidate** — soft; confirm at DDL with expected poll query |
| Template | `(school_id, certificate_type)` | Template pick | Soft — UNIQUE/natural key may suffice |

**No partitioning.**

---

## 22. Concurrency Protection

| Race | Protection |
|------|------------|
| Two IssueCertificate same enrollment+type | Identity UNIQUE; issuance UNIQUE (certificate_id, issuance_no); idempotency key |
| Two reissues | issuance_no UNIQUE; supersession app serializes per certificate_id |
| Two workers same job | Job status CAS / UNIQUE issuance_id; idempotent transition to SUCCEEDED |
| Two verification codes | GLOBAL UNIQUE on verification_code |
| Two certificate numbers | UNIQUE (school_id, certificate_number) |

**Forbidden sole reliance:** `if (!exists()) insert` without UNIQUE.

---

## 23. Retention Model

```text
Official certificate identity, issuances, artifacts metadata: durable
No retention_days / expires_at / automatic deletion columns in Phase 4.1
```

Legal retention duration **HDR** — structure assumes permanent academic retention.

---

## 24. Deferred Decisions

| ID | Item |
|----|------|
| D-CERT-01 | Verify UX when Award later revoked |
| D-CERT-03 | certificate_type value catalog |
| D-CERT-04 | Platform-shared templates (school_id null?) — Phase 4.1 forces school_id NOT NULL |
| D-CERT-05 | Correction vs reissue labeling |
| D-CERT-06 | Regenerate same issuance vs new issuance |
| D-CERT-07 | Public vs authenticated verify |
| D-SCH-01 | Adding UNIQUE `(id, school_id)` on `graduation_awards` for composite award FK — **requires separate Graduation authorization** |
| D-SCH-02 | `superseded_by_issuance_id` symmetric column |
| D-SCH-03 | Stable family certificate_number on identity |
| D-SCH-04 | generation_attempt child table |
| D-SCH-05 | Purge policy for FAILED jobs |

---

## 25. Exact Phase 4.1 DDL Scope (when authorized)

**In** a future Phase 4.1 migration unit (not now):

```text
certificates.certificate_templates
certificates.certificate_template_versions
certificates.certificates
certificates.certificate_issuances
certificates.certificate_artifacts
certificates.certificate_generation_jobs
```

Plus: PK/FK/UNIQUE/CHECK as locked; RLS ENABLE+FORCE; reject-delete on official tables; supporting indexes marked Yes above.

**Out of Phase 4.1 DDL:**

```text
Graduation ALTER / new award indexes (unless separate approval for D-SCH-01)
Permission.php / HTTP
Application CQRS
Data backfill
```

---

## 26. Explicit Non-Goals

```text
Execute migrations or DDL
Create indexes/RLS policies/triggers in the database
PHP models/repos/handlers/jobs/HTTP/permissions
Modify graduation.*, enrollment.*, exams.*, students.*
Implement IssueCertificate
Transcript / Promotion / Transfers
GetProvenance
Invent certificate_type legal catalog
```

---

## Mandatory Table Matrix

| Table | Required? | School Scoped? | Critical? | RLS? | Hard Delete? | Phase |
| ----------------------------- | --------- | -------------- | --------- | ---- | ------------ | ----- |
| certificate_templates | **Yes** | Yes | High | Yes FORCE | Reject | 4.1 |
| certificate_template_versions | **Yes** | Yes | High | Yes FORCE | Reject | 4.1 |
| certificates | **Yes** | Yes | High | Yes FORCE | Reject | 4.1 |
| certificate_issuances | **Yes** | Yes | **Critical** | Yes FORCE | Reject | 4.1 |
| certificate_artifacts | **Yes** | Yes | High | Yes FORCE | Reject | 4.1 |
| certificate_generation_jobs | **Yes** | Yes | High | Yes FORCE | Reject (default) | 4.1 |

---

## Final Gate

```text
PHASE 4.1A CERTIFICATES SCHEMA DESIGN: PASS WITH CONDITIONS

CONDITIONS:
  D-CERT-01/03–07 and D-SCH-01…05 remain deferred as listed
  graduation_award_id↔version consistency is write-time invariant
    until optional Graduation UNIQUE (id, school_id) authorized (D-SCH-01)
  certificate_type / template natural-key details finalized at DDL authoring
  Index soft candidates confirmed against real poll/verify queries at DDL unit

PHASE 4.1 DDL IMPLEMENTATION = NOT AUTHORIZED
Human approval required before any migration or DDL.
```

---

## Mutation Check

```text
PHP / MIGRATION / DDL / INDEX / RLS / TRIGGER / CODE: NONE
GRADUATION / AWARD / ENROLLMENT / GRADES: NONE

Deliverable only:
.cursor/database/phase-4/01-CERTIFICATES-SCHEMA-DESIGN-LOCK.md
```

---

```text
STOP
```
