# SIS Domain Knowledge Base

> **Purpose:** Expert Engine understands **business meaning** — not just PostgreSQL columns.  
> **Rule:** Same technical optimization on grades vs notifications = different risk tier.

---

## Domain Modules

```text
SIS Domain Knowledge
├── Student Lifecycle
├── Enrollment & Academic Year
├── Organization (Schools, Sections)
├── Curriculum & Subjects
├── Teachers & Timetable
├── Attendance
├── Exams & Grades
├── Promotion & Transfers
├── Graduation & Certificates
├── Finance & Payments
├── Documents
├── Security (Users, RBAC, RLS)
├── Audit & Compliance
└── Notifications & Communication
```

Cross-reference: `brain/student-lifecycle.md`, `database-dictionary.md`, `erd-overview.md`.

---

## Data Criticality Classification

| Level | Meaning | Optimization default tier | Hard-delete |
|-------|---------|---------------------------|-------------|
| **CRITICAL** | Legal/academic official record | Tier 3 minimum for schema change | Never |
| **HIGH** | Operational official data | Tier 2–3 | Never |
| **MEDIUM** | Supporting business data | Tier 2 | Archive preferred |
| **LOW** | Reference / config | Tier 1–2 | Deprecation OK |
| **EPHEMERAL** | Cache, queue, session | Tier 0–1 | OK |

---

## Table Criticality Map (Guideline)

| Schema.Table | Criticality | Notes |
|--------------|-------------|-------|
| exams.student_grades | CRITICAL | Never auto-optimize without integrity gate |
| enrollment.enrollments | CRITICAL | FK hub — cascade risk |
| attendance.records | HIGH | Volume table — partition OK with ADR |
| students.students | HIGH | PII + core entity |
| academic.academic_years | HIGH | Scopes all operations |
| audit.audit_logs | HIGH | Immutable trail |
| organization.schools | MEDIUM | Reference — RLS scope |
| communication.notifications | MEDIUM | Ephemeral-ish — queue driven |
| cache.* (Redis keys) | EPHEMERAL | Not in PostgreSQL |
| reports.materialized_views | MEDIUM | Rebuildable from OLTP |

Update when new tables added — sync with database-blueprint.md.

---

## Domain-Aware Inference Rules

```yaml
rule: grades_table_extra_caution
  when:
    table_criticality: CRITICAL
    optimization_type: [index_drop, partition_change, column_change]
  then:
    risk_tier: 3                    # bump minimum tier
    require: [integrity_gate, extended_staging, tech_lead_approval]
    forbidden_auto: true

rule: attendance_partition_allowed
  when:
    table: attendance.records
    table_criticality: HIGH
    table_rows: "> 100000000"
    workload_class: [dashboard, reporting]
  then:
    recommendation: "Partition review — ADR required"
    risk_tier: 3
    integrity_gate: [row_count_stable, no_orphans, sample_grade_attendance_consistency]

rule: ephemeral_cache_aggressive
  when:
    data_criticality: EPHEMERAL
    cache_hit_ratio: "< 20%"
  then:
    recommendation: "Remove cache key"
    risk_tier: 1                    # may auto with audit log
```

---

## Business Key Semantics

These columns carry domain meaning beyond FK:

| Column | Domain meaning | Expert implication |
|--------|------------------|------------------|
| `academic_year_id` | Temporal scope for all academic ops | Never query without scope |
| `school_id` | Multi-tenant isolation (RLS) | Partition/index must respect RLS |
| `student_id` | Person entity — PII | Audit all bulk changes |
| `status` (SMALLINT) | Lifecycle state — not boolean | Partial indexes need enum clarity |
| `effective_from/to` | Temporal history — not soft delete | Never DROP without archive |
| `public_id` (UUID) | External API identifier | Index for lookup only |

---

## Sensitive Operations by Domain

| Domain | High-risk operations | Required approvals |
|--------|---------------------|-------------------|
| Grades | Index drop, MV stale refresh | DBA + academic lead |
| Enrollment | FK change, section merge | Tech lead + ADR |
| Attendance | Partition detach/archive | DBA + verify summary tables |
| Certificates | Async job failure retry | Idempotency key check |
| Audit | Any write optimization | Forbidden if affects immutability |
| RLS policies | Any change | Security + Tier 4 forbidden auto |

---

## Integration with Risk Engine

```text
base_risk_tier (technical)
     +
data_criticality bump
     +
workload_class adjustment
     =
final_risk_tier
```

Example:

```text
Add index on notifications (MEDIUM) → Tier 2
Add index on student_grades (CRITICAL) → Tier 3
Same DDL pattern — different approval path
```

---

## Related

- [DATABASE-KNOWLEDGE-BASE.md](./DATABASE-KNOWLEDGE-BASE.md)
- [DATABASE-INTELLIGENCE-SAFETY.md](./DATABASE-INTELLIGENCE-SAFETY.md)
- [brain/student-lifecycle.md](../brain/student-lifecycle.md)
- [rls-policies.md](./rls-policies.md)
- [data-quality-rules.md](./data-quality-rules.md)
