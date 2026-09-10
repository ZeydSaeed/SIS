# PHASE 3C.10 — COLUMN CATALOG (CORE TABLES)

**DDL NOT EXECUTED.** Types follow SIS conventions: BIGINT identity, SMALLINT status, TIMESTAMPTZ, VARCHAR lengths as noted.

NULL semantics: prefer explicit SMALLINT states over overloaded NULL.

---

## graduation.completion_outcomes

| Column | Type | Null | Default | Meaning | Immutable | Indexed | Notes |
|--------|------|------|---------|---------|-----------|---------|-------|
| id | BIGINT GENERATED ALWAYS AS IDENTITY | NO | — | Surrogate PK | YES | PK | |
| school_id | BIGINT | NO | — | Tenant | YES | UNIQUE composite | FK schools RESTRICT |
| enrollment_id | BIGINT | NO | — | Scope | YES | UNIQUE composite | Composite FK enrollments |
| student_id | BIGINT | NO | — | Denorm | YES* | YES | Must match enrollment |
| academic_year_id | BIGINT | NO | — | Denorm | YES* | YES | Must match enrollment |
| specialization_id | BIGINT | YES | NULL | Denorm program context | YES* | optional | NULL = none on enrollment |
| current_official_version_id | BIGINT | YES | NULL | Pointer | NO | YES | Nullable until first official |
| created_at | TIMESTAMPTZ | NO | now() | Recorded | YES | NO | |
| created_by | BIGINT | YES | NULL | Actor ref | YES | NO | Opaque — no invented roles |

\*immutable after insert except via controlled admin repair — treat as identity denorm.

**UNIQUE:** `(school_id, enrollment_id)`

---

## graduation.completion_outcome_versions

| Column | Type | Null | Default | Meaning | Immutable | Indexed | Notes |
|--------|------|------|---------|---------|-----------|---------|-------|
| id | BIGINT identity | NO | — | PK | YES | PK | |
| school_id | BIGINT | NO | — | Tenant | YES | YES | |
| completion_outcome_id | BIGINT | NO | — | Parent | YES | YES | |
| version_no | INTEGER | NO | — | Monotonic | YES | UNIQUE(parent,version_no) | |
| lifecycle_status | SMALLINT | NO | — | candidate/official/superseded | NO* | partial | *official→superseded only via lineage |
| evaluation_status | SMALLINT | NO | — | not_evaluated/evaluated/… | when official YES | YES | Explicit states |
| eligibility_status | SMALLINT | NO | — | eligible/not/… | when official YES | YES | |
| eligibility_policy_version_id | BIGINT | NO | — | Policy pin | YES when official | YES | |
| calculation_version | VARCHAR(64) | NO | — | Engine pin | YES when official | NO | |
| source_fingerprint | VARCHAR(128) | YES | NULL | Complement | YES when official | NO | Not idempotency key |
| policy_fingerprint | VARCHAR(128) | YES | NULL | Complement | YES when official | NO | |
| evaluated_at | TIMESTAMPTZ | YES | NULL | Decision/eval time | YES when official | YES | |
| eligibility_determined_at | TIMESTAMPTZ | YES | NULL | | YES when official | NO | |
| is_current_official | BOOLEAN | NO | false | Current official flag | controlled | **partial unique** | |
| supersedes_version_id | BIGINT | YES | NULL | Prior | YES | NO | |
| superseded_by_version_id | BIGINT | YES | NULL | Next | controlled | NO | |
| correlation_id | VARCHAR(64) | YES | NULL | Trace | YES | YES | |
| created_at | TIMESTAMPTZ | NO | now() | Recorded | YES | NO | |
| created_by | BIGINT | YES | NULL | Actor | YES | NO | |

No mandatory GPA column (HD-22).

---

## graduation.evidence_items

| Column | Type | Null | Default | Meaning | Immutable | Indexed | Notes |
|--------|------|------|---------|---------|-----------|---------|-------|
| id | BIGINT identity | NO | — | PK | YES | PK | |
| school_id | BIGINT | NO | — | Tenant denorm | YES | YES | |
| evidence_set_id | BIGINT | NO | — | Parent | YES | YES | |
| source_type | SMALLINT | NO | — | grade/term/annual/gpa/… | YES | composite | Typed — not polymorphic bare |
| source_id | BIGINT | NO | — | Upstream id | YES | composite | |
| source_version_ref | VARCHAR(128) | YES | NULL | Upstream version | YES | NO | String/composite as needed |
| academic_year_id | BIGINT | YES | NULL | Context | YES | NO | |
| inclusion_status | SMALLINT | NO | — | included/excluded | YES | NO | |
| exclusion_reason_code | SMALLINT | YES | NULL | Why excluded | YES | NO | NULL only if included |
| captured_at | TIMESTAMPTZ | NO | now() | | YES | NO | |
| captured_by | BIGINT | YES | NULL | | YES | NO | |

**Do not store score payloads** — references only.

---

## graduation.requirement_evaluations

| Column | Type | Null | Default | Meaning | Immutable | Indexed | Notes |
|--------|------|------|---------|---------|-----------|---------|-------|
| id | BIGINT identity | NO | — | PK | YES | PK | |
| school_id | BIGINT | NO | — | | YES | YES | |
| completion_outcome_version_id | BIGINT | NO | — | | YES | YES | |
| requirement_definition_version_id | BIGINT | NO | — | | YES | YES | |
| result_status | SMALLINT | NO | — | satisfied/not/exempt/n_a/pending/blocked | YES when official | YES | missing≠satisfied |
| evaluated_at | TIMESTAMPTZ | NO | — | | YES | NO | |
| notes_ref | VARCHAR(255) | YES | NULL | Non-authoritative note key | YES | NO | Not policy text SSOT |

---

## graduation.graduation_approvals

| Column | Type | Null | Default | Meaning | Immutable | Indexed | Notes |
|--------|------|------|---------|---------|-----------|---------|-------|
| id | BIGINT identity | NO | — | PK | YES | PK | |
| school_id | BIGINT | NO | — | | YES | YES | |
| enrollment_id | BIGINT | NO | — | | YES | YES | Composite with school |
| completion_outcome_version_id | BIGINT | NO | — | | YES | YES | Must be eligible/official |
| attempt_no | INTEGER | NO | — | | YES | UNIQUE(version,attempt) | |
| decision_status | SMALLINT | NO | — | requested/approved/rejected | controlled | YES | |
| requested_at | TIMESTAMPTZ | NO | — | | YES | NO | |
| requested_by | BIGINT | YES | NULL | Opaque actor | YES | NO | No invented roles |
| decided_at | TIMESTAMPTZ | YES | NULL | | YES when decided | YES | |
| decided_by | BIGINT | YES | NULL | Opaque | YES when decided | NO | |
| decision_reason_ref | VARCHAR(128) | YES | NULL | Opaque reason code/ref | YES | NO | |
| correlation_id | VARCHAR(64) | YES | NULL | | YES | YES | |
| created_at | TIMESTAMPTZ | NO | now() | | YES | NO | |

---

## graduation.graduation_award_versions

| Column | Type | Null | Default | Meaning | Immutable | Indexed | Notes |
|--------|------|------|---------|---------|-----------|---------|-------|
| id | BIGINT identity | NO | — | PK | YES | PK | |
| school_id | BIGINT | NO | — | | YES | YES | |
| graduation_award_id | BIGINT | NO | — | | YES | YES | |
| version_no | INTEGER | NO | — | | YES | UNIQUE(award,version) | |
| graduation_approval_id | BIGINT | NO | — | Issuance basis | YES | YES | |
| completion_outcome_version_id | BIGINT | NO | — | Pin | YES | YES | |
| lifecycle_status | SMALLINT | NO | — | issued/superseded/revoked | controlled | YES | |
| is_current_issued | BOOLEAN | NO | false | | controlled | **partial unique** | WHERE issued & not revoked |
| awarded_at | TIMESTAMPTZ | NO | — | | YES | YES | |
| issued_by | BIGINT | YES | NULL | Opaque | YES | NO | |
| award_number | VARCHAR(50) | YES | NULL | Optional | YES | UNIQUE if present | Numbering policy open |
| honors_code | SMALLINT | YES | NULL | POLICY INPUT | YES | NO | Not invented values |
| supersedes_version_id | BIGINT | YES | NULL | | YES | NO | |
| superseded_by_version_id | BIGINT | YES | NULL | | controlled | NO | |
| source_fingerprint | VARCHAR(128) | YES | NULL | | YES | NO | |
| correlation_id | VARCHAR(64) | YES | NULL | | YES | YES | |
| created_at | TIMESTAMPTZ | NO | now() | | YES | NO | |

---

## graduation.revocation_records

| Column | Type | Null | Default | Meaning | Immutable | Indexed | Notes |
|--------|------|------|---------|---------|-----------|---------|-------|
| id | BIGINT identity | NO | — | PK | YES | PK | |
| school_id | BIGINT | NO | — | | YES | YES | |
| graduation_award_version_id | BIGINT | NO | — | | YES | YES | |
| revoked_at | TIMESTAMPTZ | NO | — | | YES | YES | |
| revoked_by | BIGINT | YES | NULL | Opaque authority | YES | NO | |
| revocation_reason_ref | VARCHAR(128) | YES | NULL | Opaque | YES | NO | Roles/reasons later |
| correlation_id | VARCHAR(64) | YES | NULL | | YES | YES | |
| created_at | TIMESTAMPTZ | NO | now() | | YES | NO | |

---

## Policy / requirement versions (summary)

`eligibility_policy_versions`: policy_id, version_no, lifecycle (draft/published/effective/retired), effective_from/to, content_payload JSONB **optional empty**, published_at, created_by, school_id.

`requirement_definition_versions`: definition_id, version_no, unit_kind SMALLINT (extensible codes — not seeded policy), rule_payload JSONB optional, lifecycle, school_id.

Full column lists for remaining tables follow the same patterns in implementation planning (3C.11) — identity, school_id, TIMESTAMPTZ, SMALLINT statuses, RESTRICT FKs.
