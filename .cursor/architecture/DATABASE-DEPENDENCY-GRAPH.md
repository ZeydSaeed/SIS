# Database Dependency Graph & Blast Radius

> **Purpose:** Expert Engine must know **what breaks** before recommending schema changes.  
> **Usage:** Required for Tier 2+ changes, especially DROP INDEX, ALTER COLUMN, partition changes.

---

## Dependency Graph Model

```text
Table / Column
      ↓
Foreign Keys (in + out)
      ↓
Views + Materialized Views
      ↓
Triggers + Functions
      ↓
Laravel Models + Relationships
      ↓
Services + Queries
      ↓
API Routes + Inertia Pages
      ↓
Reports + Exports + Jobs
      ↓
Cache Keys + Invalidation Map
      ↓
External Integrations (if any)
```

Document discovery checklist in [schema-change-impact.md](./schema-change-impact.md).

---

## Discovery Commands (PostgreSQL)

```sql
-- FK referencing this table
SELECT * FROM information_schema.referential_constraints
WHERE unique_constraint_schema = 'attendance' AND unique_constraint_name LIKE '%records%';

-- Views depending on table
SELECT * FROM pg_depend d
JOIN pg_rewrite r ON r.oid = d.objid
JOIN pg_class c ON c.oid = r.ev_class
WHERE d.refobjid = 'attendance.records'::regclass;

-- Indexes on table
SELECT indexname, indexdef FROM pg_indexes
WHERE schemaname = 'attendance' AND tablename = 'records';
```

Application layer: grep models, services, routes, jobs, cache-invalidation.md.

---

## Blast Radius Classification

| Radius | Scope | Example change | Default tier bump |
|--------|-------|----------------|-------------------|
| **Local** | Single low-criticality table | Index on notifications | None |
| **Module** | One domain schema | Index on attendance.records | Standard Tier 2 |
| **Cross-Module** | FK hub table | enrollment.enrollments | +1 tier |
| **System-Wide** | students, academic_year, grades | Partition on student_grades | Tier 3 + extended integrity |
| **Critical Path** | grades + enrollment + audit | Any DDL on CRITICAL | Tier 3 minimum |

```yaml
blast_radius:
  table: enrollment.enrollments
  level: cross_module
  affected:
    tables: [students.students, enrollment.sections, exams.student_grades]
    mvs: [reports.mv_enrollment_summary]
    apis: [EnrollmentController, StudentProfile]
    reports: [term_enrollment_report]
    cache_keys: [school:{id}:enrollment_count]
  estimated_impact_score: 72    # 0–100 — higher = more caution
```

Include in Explainability Package — [DATABASE-INTELLIGENCE-SAFETY.md](./DATABASE-INTELLIGENCE-SAFETY.md).

---

## Risk Scoring (Combined)

```text
final_risk = f(
  technical_risk_tier,      # Tier 0–4
  data_criticality,       # CRITICAL / HIGH / …
  blast_radius_level,       # local → system_wide
  confidence                # does NOT override tier
)
```

**Example:**

```text
Add index on notifications:  Tier 2 + MEDIUM + Local     → Tier 2
Add index on student_grades: Tier 2 + CRITICAL + System  → Tier 3
Drop index on enrollments:   Tier 3 + CRITICAL + Cross   → Tier 3 + ADR
```

**Confidence 99% + Tier 4 = still Forbidden Auto.**

---

## High-Risk Tables (SIS)

Always run full dependency graph before change:

| Table | Blast radius | Why |
|-------|--------------|-----|
| exams.student_grades | System-wide | Reports, transcripts, GPA, certificates |
| enrollment.enrollments | Cross-module | Hub for student-year-section |
| students.students | Cross-module | PII, all domains reference |
| academic.academic_years | System-wide | Scopes all academic ops |
| attendance.records | Module (volume) | Dashboards, summaries, partition ops |
| audit.audit_logs | System-wide | Compliance — append-only |

See [SIS-DOMAIN-KNOWLEDGE-BASE.md](./SIS-DOMAIN-KNOWLEDGE-BASE.md).

---

## DROP / ALTER Hard Stop

```text
Change request
     ↓
Dependency graph build (automated + app grep)
     ↓
Blast radius score
     ↓
If cross_module or higher → extended staging + integrity gate
     ↓
Human approval proportional to blast radius
     ↓
Migration with rollback tested on copy
```

Never DROP INDEX on CRITICAL-path table without: 90-day idx_scan proof + report inventory + blast radius doc.

---

## Related

- [schema-change-impact.md](./schema-change-impact.md)
- [DATABASE-INTELLIGENCE-SAFETY.md](./DATABASE-INTELLIGENCE-SAFETY.md)
- [cache-invalidation.md](./cache-invalidation.md)
- [database-blueprint.md](./database-blueprint.md)
