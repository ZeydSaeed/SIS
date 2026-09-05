# SIS Architecture Reference

> **Version:** 2.0 — Enterprise SIS Architecture for PostgreSQL  
> **Purpose:** Reference guide for developers and AI agents. No database is created from these files directly.

## Document Index

| File | Topic |
|------|-------|
| [01-principles-and-layers.md](./01-principles-and-layers.md) | Core principles, 4 layers, normalization |
| [02-infrastructure-phases.md](./02-infrastructure-phases.md) | Deployment phases, Redis, PgBouncer, replicas |
| [database-blueprint.md](./database-blueprint.md) | 80+ table blueprint with columns, types, FK, indexes |
| [indexing-matrix.md](./indexing-matrix.md) | Per-table indexing strategy |
| [scalability-and-async.md](./scalability-and-async.md) | Caching, queues, materialized views, reporting |
| [security-audit-resilience.md](./security-audit-resilience.md) | RBAC, RLS, audit, backup, DR, monitoring |
| [improvement-matrix.md](./improvement-matrix.md) | Priority matrix for all 25 improvements |
| [DATABASE-CHANGE-CHECKLIST.md](./DATABASE-CHANGE-CHECKLIST.md) | **Mandatory checklist for every DB change** |

## Architecture Overview

```
                         SIS
                          │
                 Architecture Principles
                          │
            ┌─────────────┴─────────────┐
            │                           │
          OLTP                        Cache
            │                           │
       PostgreSQL                    Redis
            │                           │
    ┌───────┼────────┐                 │
    │       │        │                 │
 Normalize Index  Partition            │
    │       │        │                 │
    └───────┼────────┘                 │
            │                           │
        Primary DB                Cache Layer
            │
      ┌─────┴─────┐
      │           │
    Replica    Reporting
      │           │
      └─────┬─────┘
            │
     Materialized Views
            │
        Dashboards
```

Cross-cutting concerns (always active):

```
Security · Audit · Monitoring · Backup · PITR · DR · Archiving · Load Testing · Migration
```

## Schema Organization

```
sis (database)
│
├── organization    — Ministry, directorates, schools, branches
├── academic        — Years, terms, levels, grades
├── vocational      — Specializations, tracks (professional education)
├── students        — Student profiles and identifiers
├── guardians       — Parents/guardians and relationships
├── admission       — Applications and intake
├── enrollment      — Yearly registration
├── teachers        — Staff and assignments
├── curriculum      — Subjects and curriculum mapping
├── timetable       — Schedules and rooms
├── attendance      — Attendance records
├── exams           — Exam definitions and sessions
├── results         — Grades and transcripts
├── promotion       — Grade advancement
├── transfers       — Inter-school moves
├── graduation      — Graduation records
├── certificates    — Certificate generation
├── documents       — File metadata
├── finance         — Fees and payments
├── communication   — Notifications
├── workflow        — Approval chains
├── security        — RBAC, permissions
├── audit           — Change trail
└── reports         — Materialized views and analytics
```

## Target Table Count

**70–100 tables** depending on final requirements. Each table represents one clear entity, relationship, or transactional record.

## Next Engineering Step

When ready to implement:

1. Review `database-blueprint.md` table by table
2. Create versioned migrations: `V001__create_organization.sql`
3. Build ERD from blueprint relationships
4. Apply indexing matrix after real query patterns emerge
5. Add partitioning only to high-growth tables

**Do not create all 100 tables on day one.** Implement by domain module following the student lifecycle priority.
