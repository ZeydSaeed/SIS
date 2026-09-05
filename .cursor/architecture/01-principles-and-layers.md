# Architecture Principles & Data Layers

## 1. Core Principle

The system manages the **complete student lifecycle**, not isolated CRUD on students and grades.

Every academic operation must be scoped to `academic_year_id`.

## 2. Ten Mandatory Rules

1. No duplicate data storage.
2. Never delete official academic history.
3. Every important operation links to `academic_year_id`.
4. Every relationship uses Foreign Keys with indexes where appropriate.
5. Transactional tables include temporal columns (`created_at`, `updated_at`, `effective_from`, `effective_to`).
6. Heavy operations never run inside HTTP request lifecycle.
7. Heavy reports read from materialized views / reporting tables, not raw OLTP joins.
8. Every important query must be measured with `EXPLAIN ANALYZE`.
9. Add indexes only based on real measured usage.
10. Design must allow growth without full system rebuild.

## 3. Four Architecture Layers

### Layer 1 — Data Integrity (P0)

```
Normalization → PK/FK → CHECK Constraints → Transactions → Audit Trail
```

- Target: 3NF in OLTP
- Controlled denormalization **only** in reporting/cache layers
- Use database constraints, not just application validation:

```sql
CHECK (grade >= 0 AND grade <= 100)
UNIQUE (student_id, academic_year_id)  -- one active enrollment per year
```

### Layer 2 — Performance (P0–P1)

```
Optimized Data Types → BIGINT IDs → Composite Indexes → Partial Indexes → Covering Indexes → Partitioning
```

PostgreSQL corrections vs SQL Server/MySQL:

| Wrong (other DBs) | Correct (PostgreSQL) |
|-------------------|---------------------|
| TINYINT | smallint |
| Filtered Index | Partial Index (`WHERE …`) |
| Index Reorganize | VACUUM / ANALYZE / REINDEX |
| Random UUID as every PK | BIGINT GENERATED ALWAYS AS IDENTITY |

### Layer 3 — Scalability (P1–P2)

```
Redis Cache → PgBouncer → Async Queues → Read Replicas → Reporting DB → Materialized Views
```

- PostgreSQL = source of truth
- Redis = cache only (reference data, dashboards, sessions)
- Async for: PDF generation, bulk import/export, mass notifications, certificate batches

### Layer 4 — Resilience (P0)

```
Backup → WAL/PITR → Replication → DR → Monitoring → Alerting → Archiving → Load Testing → Migration Versioning
```

## 4. Normalization Strategy

```
1NF → 2NF → 3NF → Controlled Denormalization (reporting only)
```

**Never store:**

```
student | subject1 | subject2 | mark1 | mark2 | absence1 | absence2
```

**Always store:**

```
students → enrollments → curriculum_subjects → subjects
                                    ↓
                          grades, attendance, exams
```

**Denormalization allowed in:**

- Materialized views (`reports.*`)
- Redis cache (dashboard aggregates)
- Reporting tables (pre-computed statistics)

## 5. Data Types Standard

```sql
-- Primary keys (internal)
id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY

-- Foreign keys
student_id BIGINT NOT NULL REFERENCES students.students(id)

-- Status / small enums
status SMALLINT NOT NULL DEFAULT 1

-- Codes and identifiers
student_code VARCHAR(50) NOT NULL
national_id  VARCHAR(20)

-- Names and text
full_name    VARCHAR(255) NOT NULL
notes        TEXT

-- Dates
birth_date       DATE
effective_from   DATE NOT NULL
effective_to     DATE
attendance_date  DATE NOT NULL

-- Timestamps
created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
archived_at TIMESTAMPTZ

-- Money
amount NUMERIC(12, 2) NOT NULL

-- UUID (external/public IDs only)
public_id UUID DEFAULT gen_random_uuid()
```

## 6. Soft Delete Policy

Do **not** use `deleted = true` everywhere.

| Entity Type | Strategy |
|-------------|----------|
| Official academic records | Never delete; use status + effective_to |
| Reference data (subjects, rooms) | status + archived_at |
| Draft/temporary data | May use soft delete |
| Audit logs | Never delete |

## 7. Temporal / Historical Data

Track changes through enrollment history, not by overwriting current fields:

```
students.students          — identity (stable)
enrollment.enrollments   — per-year placement (temporal)
transfers.records          — transfer events
promotion.records          — promotion events
```

## 8. Bulk Operations

For imports of 10,000+ records:

```
COPY / Bulk Insert → Batch Processing → Background Job → Progress UI
```

Never: 10,000 individual INSERT requests.

## 9. Document Storage

Files (PDF, images) stored in **object storage**. PostgreSQL stores metadata only:

```
documents.student_documents
  id, student_id, document_type, storage_key, file_name, mime_type, size, hash (SHA-256), created_at
```

## 10. Idempotency

Required for: payments, notifications, imports, certificates, external integrations.

Use idempotency keys to prevent duplicate operations from retried requests.

## 11. Correlation ID

Every HTTP request gets a correlation ID (e.g. `REQ-2026-000123`) propagated through:

```
API → Service → Queue → Database → Audit Log
```

Enables end-to-end tracing when errors occur.
