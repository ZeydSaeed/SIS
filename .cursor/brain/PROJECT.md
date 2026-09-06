# SIS — Student Information System

## Project Identity

Enterprise Student Information System (SIS) designed for long-term growth, high concurrency, and 20+ years of academic data retention.

**Stack:** Laravel 13 + Inertia.js + React 19 + PostgreSQL + Redis  
**Status:** Reference documentation v3.1 complete — no database deployed.  
**Score:** 93/100 (Design) · ~60/100 (Operational — Phases 2–6 pending)
**Baseline scale:** 45,000 students, 20 schools, 10 years — see `capacity-planning.md` (45K is baseline, not ceiling)

## What This System Is

Not merely `Students + Grades + Attendance`. It manages the **complete student lifecycle**:

```
Admission → Enrollment → Attendance → Exams → Grades → Promotion → Transfer → Graduation → Certificate
```

Every academic operation is scoped to an **Academic Year**.

## Core Domains

| Domain | Purpose |
|--------|---------|
| `organization` | Schools, branches, directorates, ministry hierarchy |
| `academic` | Academic years, terms, levels, grades |
| `students` | Student profiles, identifiers, status |
| `guardians` | Parent/guardian relationships |
| `admission` | Applications, acceptance, intake |
| `enrollment` | Yearly registration, sections, specializations |
| `curriculum` | Subjects, curriculum mapping |
| `teachers` | Teacher assignments, qualifications |
| `timetable` | Schedules, rooms, periods |
| `attendance` | Daily/session attendance records |
| `exams` | Exam definitions, sessions, seating |
| `results` | Grades, GPA, transcripts |
| `promotion` | Grade advancement rules and records |
| `transfers` | Inter-school/section transfers |
| `graduation` | Graduation eligibility and records |
| `certificates` | Certificate generation and verification |
| `documents` | File metadata (storage external) |
| `finance` | Fees, payments, scholarships |
| `communication` | Notifications, messages |
| `workflow` | Approval chains, status transitions |
| `security` | RBAC, permissions, scopes |
| `audit` | Immutable change trail |
| `reports` | Materialized views, analytics |

## Mandatory Design Rules

1. No duplicate data storage.
2. Never delete official academic history.
3. Every important operation links to `academic_year_id`.
4. Every relationship uses Foreign Keys.
5. Transactional tables include temporal columns.
6. Heavy operations run asynchronously (queues).
7. Heavy reports read from materialized views, not raw OLTP joins.
8. Every important query must be measured (`EXPLAIN ANALYZE`).
9. Add indexes only based on real usage — never auto-index every FK.
10. Design must allow growth without full rebuild.
11. **DO NOT OPTIMIZE FOR A NUMBER. OPTIMIZE FOR A MEASURED WORKLOAD.**
12. Optimization must never change business correctness (Correctness > Performance).
13. **PostgreSQL = source of truth.** Intelligence layer recommends — never auto-modifies schema.
14. Self-Healing limited to Tier 1 safe ops (pool, replica routing) — see SELF-HEALING-RUNBOOK.md.

## Four Architecture Layers

```
Layer 1 — Data Integrity    : Normalization, PK/FK, Constraints, Transactions, Audit
Layer 2 — Performance       : Indexes, Partitioning, Query Optimization, Data Types
Layer 3 — Scalability       : Redis, PgBouncer, Queues, Replicas, Materialized Views
Layer 4 — Resilience        : Backup, PITR, DR, Monitoring, Archiving, Load Testing
```

## What We Do NOT Claim

> "The system is guaranteed to run flawlessly for 20 years."

**Correct statement:**

> The system is architected for 20+ years of controlled data growth, maintainability, scalability, backup recoverability, and operational continuity.

## Reference Files

- Index: `.cursor/architecture/README.md`
- Work plan: `.cursor/architecture/WORK-PLAN.md`
- Intelligence layer: `.cursor/architecture/DATABASE-INTELLIGENCE-LAYER.md`
- Knowledge base: `.cursor/architecture/DATABASE-KNOWLEDGE-BASE.md`
- Adaptive governance: `.cursor/architecture/DATABASE-ADAPTIVE-GOVERNANCE.md`
- Capacity model: `.cursor/architecture/capacity-planning.md`
- Performance budget: `.cursor/architecture/PERFORMANCE-BUDGET.md`
- Blueprint: `.cursor/architecture/database-blueprint.md` (**89 tables**)
- ERD: `.cursor/architecture/erd-overview.md`
- Dictionary: `.cursor/architecture/database-dictionary.md`
- Normalization + CQRS: `.cursor/architecture/normalization-and-cqrs.md`
- ADRs: `.cursor/architecture/adr/`
- Cursor rules: `.cursor/rules/`
