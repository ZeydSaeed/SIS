# PHASE 4.0B — CERTIFICATES DOMAIN DESIGN LOCK

**Date:** 2026-09-11  
**Mode:** AUDIT + DOMAIN DESIGN ONLY  
**Authorization:** Human selected Phase 4 Candidate A — Certificates  
**Predecessor:** `.cursor/database/phase-4/PHASE-4-READINESS-AND-SCOPE-LOCK.md`

```text
PHASE 4.0B — CERTIFICATES DOMAIN DESIGN LOCK
IMPLEMENTATION: NOT AUTHORIZED
PHASE 4.1: NOT AUTHORIZED
NO MIGRATIONS · NO DDL · NO CODE · NO HTTP · NO PERMISSIONS
NO GRADUATION / AWARD / ENROLLMENT / GRADES MUTATION
```

---

## 1. Executive Summary

Certificates is locked as an **official downstream consumer** of LIVE Graduation Award facts.

```text
Graduation Award (SSOT for award)
        ↓ pin (version)
Certificate issuance (Certificates owns lifecycle)
        ↓
Certificate artifact (object storage + metadata)
        ↓
Verification (public identifier → status + source facts)
```

**Source pin (LOCKED):** `graduation_award_version_id` (Option B).

**Rejected:** blueprint `graduation_id → graduation.records`, enrollment-only pin as sole source, MAX/id/created_at/is_current_issued fallbacks, invented permissions, cascade delete on award revoke, PDF generation inside DB transactions.

```text
PHASE 4.0B CERTIFICATES DESIGN LOCK: PASS WITH CONDITIONS
PHASE 4.1 IMPLEMENTATION = NOT AUTHORIZED
Human approval required before Phase 4.1.
```

---

## 2. Repository Audit

| Area | Finding | Classification |
|------|---------|----------------|
| `database/migrations/*certificate*` | **None** creating cert tables | Absent |
| `certificates` PostgreSQL schema | Created empty via `SchemaHelper` | Active reserved schema (no tables) |
| `app/**` Certificate classes | **None** | Absent |
| `routes/**` | **None** | Absent |
| `tests/**` certificate suites | **None** | Absent |
| `Permission.php` certificate constants | **None** | Absent |
| Blueprint `certificates.*` | 3-table sketch | **Obsolete / non-authoritative design** |
| Docs (PHASE-D, WORK-PLAN, api-conventions verify path, GenerateCertificatesJob sketch) | Planning / sketches | Stale documentation |
| ADR-003 | Public verify via `verification_code` | Authoritative **pattern** (not schema) |
| Admission `storage_key` + `file_hash` | LIVE metadata pattern | Reusable storage boundary |
| Graduation Award | LIVE schema + CQRS + reads | Authoritative consumer foundation |

**Partial implementation of Certificates:** none.  
**Prerequisite Award domain:** LIVE and verified.

---

## 3. Existing Certificate Blueprint Findings

Blueprint (`database-blueprint.md` § certificates):

| Blueprint element | Status |
|-------------------|--------|
| `templates` / `issued_certificates` / `generation_jobs` | Sketch only — **not LIVE** |
| `issued_certificates.graduation_id` → `graduation.records` | **STALE** (C-3C8-07; 3C.7–3C.8; phase-3c-11 STALE audit) |
| `student_id` as primary academic context | **Insufficient** — Enrollment is academic context |
| Global UNIQUE `certificate_number` / `verification_code` | Intent useful; **school-scope redesign required** at schema phase |
| `storage_key` / `file_hash` | Aligns with SIS metadata pattern |
| `generation_jobs.idempotency_key` | Aligns with governance; reuse `audit.idempotency_keys` |

```text
graduation_id MUST NOT be reused without explicit redesign.
THIS LOCK FORBIDS graduation_id as the Award pin.
```

---

## 4. Graduation Award Contract Verification

Verified against LIVE migrations + Application code (not gate prose alone).

| # | Contract | Evidence | Status |
|---|----------|----------|--------|
| 1 | Award identity UNIQUE `(school_id, enrollment_id)` | `graduation_awards_school_enrollment_uq` | **CONFIRMED** |
| 2 | Award version UNIQUE `(graduation_award_id, version_no)` | `graduation_award_versions_uq` | **CONFIRMED** |
| 3 | Current-issued pointer `current_issued_version_id` | Column + FK on awards head | **CONFIRMED** |
| 4 | Lifecycle on versions (issued/superseded/revoked) | SMALLINT + revoke → `3` | **CONFIRMED** |
| 5 | Revocation: `is_current_issued=false`, `lifecycle_status=3`; append `revocation_records` | Write repo | **CONFIRMED** |
| 6 | Revocation does **not** clear `current_issued_version_id` | Write repo — no head UPDATE | **CONFIRMED** |
| 7 | School scope on award + versions | `school_id` + RLS FORCE | **CONFIRMED** |
| 8 | Enrollment identity | UNIQUE school+enrollment | **CONFIRMED** |
| 9 | No hard delete | Reject-delete triggers | **CONFIRMED** |
| 10 | Pointer-only current read | `LEFT JOIN … ON v.id = a.current_issued_version_id` | **CONFIRMED** |

**Forbidden current-resolution shortcuts (absent from GetGraduationAward):**

```text
MAX(id) · MAX(version_no) · MAX(created_at) · ORDER BY is_current_issued
```

**History:** `GetOutcomeHistory` lists all award versions `version_no ASC` — Certificates must not collapse history via pointer alone.

**Material implication for Certificates:** after Award revoke, pointer may still reference a revoked version. Certificate design must pin a **specific version row** and treat Award lifecycle as a separate verification input — not invent new Award resolution.

**Contradiction check:** No code contradiction with pointer-only rule found. Write-path gap (official/supersession unused) does not block Certificates pinning a persisted award version.

---

## 5. Certificate Source Pin Decision

### Options evaluated

| Option | Pin | Pros | Cons |
|--------|-----|------|------|
| **A** | `graduation_award_id` | Stable award head | Does **not** answer which version/facts; reissue/revoke ambiguity; requires heuristic “current at issue time” |
| **B** | `graduation_award_version_id` | Exact facts; reproducible; audit-grade | Requires version exists at issue; must validate school scope |
| **C** | `school_id + enrollment_id` only | Simple | No Award linkage; invites second eligibility engine; fails “which award facts?” |

### Recommendation (LOCKED)

```text
AUTHORITATIVE SOURCE PIN = graduation_award_version_id
```

**Option B is locked.**

### Rationale

1. **Reproducibility:** Answers “Exactly which authoritative Graduation Award facts produced this certificate?” without reconstruction.  
2. **Award version already pins** `completion_outcome_version_id` + `graduation_approval_id` — certificate inherits that chain by reference.  
3. **Revocation/reissue:** Multiple award versions can exist; head pointer is not a frozen historical statement (and may remain on revoked version).  
4. **Does not invent** new Award mechanisms — consumes existing version row.  
5. Option A alone fails documentary integrity under multi-version history.  
6. Option C alone recreates Graduation eligibility risk.

### Locked relationship

```text
CertificateIssuance
    ↓ FK (authoritative)
graduation_award_versions.id
    ├── graduation_award_id
    ├── completion_outcome_version_id
    ├── graduation_approval_id
    ├── lifecycle_status / is_current_issued / awarded_at / …
    └── school_id
```

### Denormalized scope (LOCKED as required companions, not alternate pins)

Issuance **must also persist** (denorm / tenant / listing):

```text
school_id
enrollment_id
graduation_award_id
```

Rules:

- `school_id` must match award version’s `school_id`.  
- `enrollment_id` / `graduation_award_id` must match the award head for that version.  
- These are **not** substitutes for `graduation_award_version_id`.  
- `student_id` may be denorm for display; **must not** be sole academic identity.

### Issuance-time Award validity (LOCKED minimum)

At IssueCertificate (Application policy, later unit):

```text
REQUIRE:
  award version exists under school_id
  award version.lifecycle_status is issuable for certificates
```

**Issuable default (LOCKED pending institutional override):** only versions with `lifecycle_status = 1` (issued) may be used to create a **new** certificate issuance.

Revoked award versions (`lifecycle_status = 3`) **must not** authorize **new** issuance.

Whether an **already-issued** certificate remains publicly “valid” after later award revoke is a **verification policy** — see §10 / Deferred.

### Forbidden

```text
graduation_id
MAX(version_no) / MAX(id) / MAX(created_at) to pick “the” award version at issue
is_current_issued ORDER BY fallback when pointer null
certificate.current = true as Award substitute
```

---

## 6. Certificate Identity

### Distinctions (LOCKED)

| Concept | Role |
|---------|------|
| **Certificate identity** | Durable business record for a credential issuance family within school+enrollment (+ type) |
| **Certificate issuance / version** | One official issuance attempt/result that pins an award version + template pin + verification ids |
| **Certificate artifact** | Generated file metadata + storage reference |
| **Verification identity** | Public opaque code/number used externally |
| **Generation job** | Async work unit to produce artifact(s) |

### Cardinality (LOCKED)

```text
NOT assumed: one certificate per student
NOT assumed: one certificate per enrollment forever

LOCKED:
  0..N certificate issuances per (school_id, enrollment_id)
  differentiated by certificate_type / template family and reissue/replacement chain
```

Evidence: multi-type certificates (graduation, leaving, etc.) are anticipated in blueprint `certificate_type`; no repo evidence forces singleton enrollment certificate.

### Authoritative identity axes (conceptual)

```text
Internal PK: BIGINT IDENTITY (ADR-003) — never public URL
Business/listing: school_id + enrollment_id + certificate_type + issuance_no (or equivalent)
Public verify: verification_code (opaque)
Human-facing: certificate_number (school-scoped uniqueness — exact UNIQUE shape deferred to schema Design)
```

`certificate_id` alone is storage identity, not public identity.

### Reissue / replacement / correction (LOCKED concepts)

| Concept | Meaning |
|---------|---------|
| Original issuance | First official issuance for a type/context |
| Replacement / reissue | New issuance that **supersedes** a prior issuance (new pin/facts/artifact allowed) |
| Correction | Policy-deferred subtype of replacement — **HDR** for legal wording |
| Revocation | Issuance marked revoked; history retained |

Prior issuances remain persisted (no hard delete).

---

## 7. Template Identity / Versioning

### Requirements (LOCKED)

Templates need:

```text
template identity
template version (or immutable revision)
school scope (school-owned and/or platform-shared — HDR for shared templates)
document / certificate_type
locale/language (slot; values HDR)
status (draft/active/retired)
effective dating (slot)
layout/configuration reference (not free mutable blob after pin)
```

### Issuance must pin template immutably (LOCKED)

At issuance, pin **at least one** of:

```text
template_version_id   (preferred if versions exist)
OR template_id + template_content_hash / revision
```

**Rule:** After an official certificate is issued, changing the live template **must not** alter the meaning of that issuance. Reproduction/audit uses the **pinned** template revision/hash.

Exact physical columns deferred to Phase 4.1 schema Design — conceptual contract locked.

---

## 8. Artifact Model

```text
Certificate Issuance (official record)
        ↓ 0..N artifacts over time (generation retries / regeneration)
Artifact metadata (DB)
        ↓
Object / file storage (bytes)
```

### Artifact properties (conceptual)

```text
storage_key
content_type
checksum / file_hash (SHA-256 pattern — security-audit-resilience)
byte_size
generated_at
generator_version
pinned template revision/hash
link to generation_job / attempt
```

### Immutability (LOCKED)

| Action | Allowed? |
|--------|----------|
| Official issuance record hard-deleted | **No** |
| Artifact bytes silently replaced changing meaning | **No** |
| New artifact row for regeneration attempt | **Yes** |
| Mark prior artifact superseded after successful replacement issuance | **Yes** |
| DB BLOB as primary store | **No** (unless future ADR) — follow admission metadata pattern |

Regeneration of the **same** issuance’s PDF for technical repair may create a new artifact row and mark previous storage obsolete **only if** content hash policy allows equivalence — **HDR** for “same content vs new issuance.” Default: prefer **new superseding issuance** when content/source pins change.

---

## 9. Lifecycle State Machine

Do **not** blindly adopt long enum lists. Minimum durable states (LOCKED conceptual set):

| State | Meaning | Externally verifiable? |
|-------|---------|------------------------|
| `PENDING_GENERATION` | Issuance intent persisted; artifact not ready | Limited (internal / staff) |
| `ISSUED` | Official; artifact available (or verified ready) | **Yes** |
| `FAILED` | Generation failed; issuance not officially usable | No (or “unavailable”) |
| `REVOKED` | Certificate issuance revoked | **Yes** (revoked) |
| `SUPERSEDED` | Replaced by later issuance | **Yes** (superseded / see successor) |

`DRAFT` is **optional** and OUT of minimum official path unless Design needs staff staging — default **OUT** of Phase 4.1 minimum.

### Transitions (LOCKED)

| From | To | Actor/system | Forbidden |
|------|----|--------------|-----------|
| — | PENDING_GENERATION | IssueCertificate command (txn) | Skip to ISSUED without durable intent when async required |
| PENDING_GENERATION | ISSUED | Generation consumer success | Jump to REVOKED without ISSUED (unless cancel policy — deferred) |
| PENDING_GENERATION | FAILED | Generation consumer failure | Auto-delete issuance |
| FAILED | PENDING_GENERATION | Controlled retry | Infinite silent retry without attempt bound |
| ISSUED | REVOKED | RevokeCertificate command | Hard delete |
| ISSUED | SUPERSEDED | Reissue/replacement command | Silent overwrite |
| REVOKED / SUPERSEDED | ISSUED | — | **Forbidden** (create new issuance instead) |

States are **persisted** on the issuance (not derived solely from Award flags).

---

## 10. Revocation / Supersession Rules

### Separation (LOCKED)

```text
Award revoked     ≠  Certificate deleted
Certificate revoked ≠  Award mutated
```

No automatic cascade **delete**. Cascade **revoke** of certificates when Award is revoked is **NOT authorized** by this lock (would invent policy).

### Scenarios

| Scenario | Certificate behavior |
|----------|----------------------|
| Award version revoked; certificate already ISSUED | Certificate row remains; verification policy decides display (Deferred D-CERT-01) |
| Certificate REVOKED; Award still issued | Certificate shows revoked; Award unchanged |
| Reissue | New issuance SUPERSEDES prior; prior retained |

### Validity model (LOCKED)

```text
Certificate validity is INDEPENDENTLY PERSISTED
  (certificate lifecycle state)

Verification MAY ALSO report Award version lifecycle as SOURCE CONTEXT
  (combination presentation — not silent deletion)
```

Exact public wording when Award revoked but certificate ISSUED: **DEFERRED (D-CERT-01)** — do not invent legal text.

---

## 11. Verification Contract

### Public identity (LOCKED)

```text
verification_code = opaque public verification identifier
certificate_number = human-facing number (not sole security secret)
```

Aligns with ADR-003: do not expose internal BIGINT in public verify URLs.

### Conceptual flow

```text
public verification request (identifier)
        ↓
lookup issuance by verification_code (tenant-safe design)
        ↓
return: authenticity + certificate lifecycle status
        + minimal authoritative source facts (school label, issue date, pin refs as policy allows)
        ↓
MUST NOT dump private student PII / internal IDs / DB structure
```

### Security (design)

- High-entropy `verification_code` (unpredictable; not sequential id)  
- Rate-limit / anti-enumeration at HTTP unit (later)  
- School isolation: verify path must not leak cross-school existence beyond intended public surface (**HDR** for public vs authenticated verify)

HTTP routes: **OUT OF SCOPE** (api-conventions sketch is non-authoritative for implementation).

---

## 12. Async Generation Contract

```text
IssueCertificate
  → persist issuance (PENDING_GENERATION) + pins + verification ids
  → idempotency store
  → outbox event
  → (after commit) Generation Job consumer
  → produce artifact → storage
  → mark ISSUED (or FAILED)
```

### Rules (LOCKED)

| Concern | Contract |
|---------|----------|
| Queue boundary | Generation **outside** DB transaction |
| Job identity | Durable generation_job / attempt linked to issuance |
| Idempotency | Command-level key in `audit.idempotency_keys`; job-level duplicate suppression |
| Retry | Safe: must not create second official issuance for same command key |
| Failure | FAILED state + bounded retries; dead-letter operational (**HDR** ops) |
| Concurrency | UNIQUE business keys prevent duplicate official numbers/codes |
| Timeout | Operational HDR |

`GenerateCertificatesJob` name in old docs is **non-binding**.

---

## 13. Transaction Boundary

### Inside UnitOfWork transaction (LOCKED)

```text
assert school / authority (fail-closed)
validate award version pin + school/enrollment consistency
create/ensure certificate identity + issuance row (PENDING_GENERATION)
assign certificate_number / verification_code (durable)
pin award version + template revision/hash
stage outbox message
store idempotency response payload
```

### Outside transaction (LOCKED)

```text
PDF / document rendering
object storage upload
long I/O
```

**Rejected:** long-running PDF generation inside DB transaction.

---

## 14. Outbox / Idempotency Contract

Reuse **only**:

```text
audit.outbox_messages
audit.idempotency_keys
```

via existing `OutboxRepository` / `IdempotencyStore` — **no second framework**.

| Topic | Contract |
|-------|----------|
| Event boundary | Stage after business write, same txn (Graduation pattern) |
| Idempotency scope | `(key, command_name)` — IssueCertificate / RevokeCertificate / Reissue… |
| Replay | Return stored result; no duplicate issuance |
| Consumer | Outbox dispatcher → generation job; at-least-once with idempotent handlers |

Event type names: **PROPOSED at implementation** — do not invent catalog here beyond dotted domain style (e.g. `certificates.certificate_issuance_requested`).

---

## 15. RLS / Security Contract

| Requirement | Contract |
|-------------|----------|
| `school_id` on all school-scoped certificate tables | **Required** |
| ENABLE RLS + FORCE RLS | **Required** (Graduation/Grades pattern) |
| Cross-school access | Fail closed |
| Policy names | **Not invented in this lock** — defined at migration unit with GraduationTenantProtection-style helper or equivalent |
| Application authority | Fail-closed port (new Certificates authority or extended fail-closed config) — **no Permission.php invention** |

Certificates must not weaken Graduation RLS.

---

## 16. Delete / Retention Policy

```text
DELETE (hard) of official certificate issuance / artifact metadata = REJECTED
```

| Mechanism | Policy |
|-----------|--------|
| Hard delete | Forbidden for official issuances |
| Soft delete | Prefer **lifecycle** (REVOKED/SUPERSEDED) over ambiguous deleted_at-only |
| Revocation / supersession | Authoritative history tools |
| Retention duration | **HDR** (legal) — structure supports permanent academic retention default |
| Future trigger | Reject-delete trigger **required at schema implementation** (like Graduation) |

---

## 17. Storage Boundary

```text
Database: metadata only
Object/file storage: artifact bytes
Provider: NOT hard-coded (disk/S3/etc. via storage abstraction)
```

Follow LIVE admission pattern: `storage_key` + `file_hash`.

---

## 18. Audit Requirements

Must remain auditable for certificate lifetime:

| Category | Facts |
|----------|-------|
| Business source | school, enrollment, award_id, **award_version_id**, template pin |
| Issuance | who/when (opaque actor ids), correlation_id, certificate_number, verification_code |
| Artifact | storage_key, hash, generator version, job/attempt |
| Lifecycle | revoke/replace actors/times/reason_ref (opaque) |

Separate business pins from operational audit bus (outbox) — do not duplicate entire payloads unnecessarily.

---

## 19. Performance Contract

### Expected access paths (future indexes — **not created now**)

```text
verification_code → issuance (hot public path)
(school_id, certificate_number)
(school_id, enrollment_id) listing
(school_id, status) generation jobs
award_version_id reverse lookup (support/audit)
```

No premature partitioning. No EXPLAIN required at design-only stage. Bulk generation = queue chunks (batch-write-patterns intent).

---

## 20. Authorization Dependencies

| Item | Status |
|------|--------|
| HTTP / routes / controllers | **OUT OF SCOPE** |
| Permission.php constants | **Do not invent**; none exist today |
| HD-31-G style catalog | **Dependency** before any HTTP certificate unit |
| Application fail-closed allow-lists | Acceptable interim (Graduation precedent) for CQRS-only units |

```text
Authorization for Phase 4.1 schema/CQRS ≠ authorization for HTTP verify endpoint
```

---

## 21. Domain Ownership Boundary

```text
Graduation
  owns: completion, evaluation, approval, award eligibility/issuance/revocation SSOT

Certificates
  owns: certificate issuance, artifact, verification lifecycle, template pins for certs

Certificates MUST NOT:
  mutate Graduation eligibility/completion/award rows
  recalculate eligibility
  become a second Award engine
  treat StudentStatus as SSOT

Certificates MAY consume (read/pin):
  Graduation Award / Award Version
  Enrollment
  Student (denorm)
  School
```

---

## 22. Risks

| Risk | Mitigation (design) |
|------|---------------------|
| Stale `graduation_id` reused | Forbidden by this lock |
| Pinning award head only | Forbidden — version pin locked |
| Pointer-only Award read used as sole cert history | Cert pins version; history via cert issuances |
| Award revoke vs cert validity confusion | Independent cert lifecycle + deferred verify policy |
| Duplicate issuance on retry | Idempotency + UNIQUE numbers/codes |
| Predictable verification codes | High-entropy opaque codes |
| Cross-school leak | school_id + RLS FORCE + app assert |
| PDF in transaction | Forbidden |
| Cascade delete | Forbidden |
| Invented permissions | Forbidden |
| Forged storage_key | Hash + authz on issue/generate; no public write |
| Concurrent issue races | UNIQUE constraints + txn |

---

## 23. Deferred Decisions

| ID | Decision | Notes |
|----|----------|-------|
| D-CERT-01 | Public verify behavior when Award version later revoked but cert ISSUED | Combination display vs auto-invalidate — legal/HDR |
| D-CERT-02 | Exact UNIQUE shapes for certificate_number / verification_code (global vs school-scoped) | Schema unit |
| D-CERT-03 | Certificate type catalog values | Do not invent enums beyond slot |
| D-CERT-04 | Shared vs school-only templates | HDR |
| D-CERT-05 | Correction vs reissue legal wording | HDR |
| D-CERT-06 | Whether regenerate-same-issuance allowed for identical hash | HDR |
| D-CERT-07 | Public anonymous verify vs authenticated verify | Security HDR + HTTP later |
| D-CERT-08 | Outbox event_type string catalog | At implementation |
| D-CERT-09 | Multi-certificate types per enrollment product matrix | Product HDR |

---

## 24. Phase 4.1 Preconditions

Before `APPROVED — IMPLEMENT PHASE 4 UNIT 4.1` (schema):

1. This Design Lock accepted (PASS WITH CONDITIONS acknowledged).  
2. Human explicitly authorizes Phase 4.1.  
3. Schema Design resolves D-CERT-02 uniqueness + table list under `certificates` schema.  
4. FK pin to `graduation.graduation_award_versions(id)` (+ school composite as required).  
5. RLS ENABLE+FORCE plan without weakening Graduation.  
6. Reject-delete plan for official tables.  
7. Blueprint update to mark `graduation_id` superseded and document new pin.  
8. **No** Permission.php / HTTP in 4.1 unless separately approved.  
9. database-change skill + checklist.

---

## 25. Explicit Non-Goals

```text
Migrations / CREATE TABLE / indexes / RLS DDL / triggers
Eloquent models / repositories / CQRS handlers / DTOs
Queue job implementation / storage provider implementation
PDF generation implementation
HTTP / controllers / routes / Permission.php
Transcript / Promotion / Transfers
GetProvenance
Graduation remediation (PublishAward, official promotion, supersession writes, EvidenceSet)
Evaluation catalogs
StudentStatus sync
Changes to Award / Completion / Enrollment / Grades write or read contracts
```

---

## Forbidden Shortcuts Checklist

| Shortcut | Status |
|----------|--------|
| `graduation_id` unexplained legacy FK | **REJECTED** |
| `student_id` sole academic identity | **REJECTED** |
| MAX(version_no/id/created_at) resolution | **REJECTED** |
| `certificate.current` replacing Award pointer | **REJECTED** |
| Hard delete official certificate | **REJECTED** |
| Sync PDF in DB transaction | **REJECTED** |
| Auto cascade Award revoke → cert delete | **REJECTED** |
| Invented permissions | **REJECTED** |
| Invented academic eligibility rules | **REJECTED** |

---

## Mutation Check

```text
PHP / DTO / REPO / HANDLER / TEST: NONE
DATABASE / MIGRATION / INDEX / RLS / HTTP / PERMISSION: NONE
GRADUATION / AWARD / ENROLLMENT / GRADES: NONE

Deliverable only:
.cursor/database/phase-4/0B-CERTIFICATES-DESIGN-LOCK.md
```

---

```text
PHASE 4.0B CERTIFICATES DESIGN LOCK: PASS WITH CONDITIONS

CONDITIONS:
  D-CERT-01…09 deferred policies explicitly listed
  Schema uniqueness (D-CERT-02) resolved in Phase 4.1 design before DDL
  HTTP/Permission remain OUT until separate authorization

PHASE 4.1 IMPLEMENTATION = NOT AUTHORIZED
Human approval required before Phase 4.1.

STOP
```
