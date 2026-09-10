# 00 — Database Architecture

**Status:** Phase 1 — decisions locked (ADR-020)  
**Engine:** PostgreSQL 18+  
**Application:** Laravel 13 + Clean Architecture  
**SSOT for implemented columns:** `.cursor/architecture/database-blueprint.md` (**87** blueprint objects)  
**Live audit:** [SIS-DATABASE-MASTER-AUDIT.md](./SIS-DATABASE-MASTER-AUDIT.md)  
**Decision lock:** [ADR-020](../../.cursor/architecture/adr/ADR-020-phase-1-database-architecture.md)

---

## 1. Purpose

This database is the foundation of an **Enterprise Educational Information Platform** combining:

- **SIS** — students, academics, enrollment, attendance, assessment, vocational workshops  
- **ERP** — HR/payroll, inventory, facilities, expanded finance (**phased** — never one wave)  
- **Shared** — identity/auth, audit, security/RLS, reporting, intelligence (recommend-only)

Correctness, tenant isolation, auditability, and long-horizon evolution outrank table count.

---

## 2. Locked decisions (Phase 1)

| ID | Lock |
|----|------|
| D1 | BIGINT IDENTITY internal PKs; no UUID PK migration |
| D2 | Preserve existing schemas; `branches` = campuses |
| D3 | Domain-by-domain phases with gates |
| D4 | `admission` schema in a later phase; no student identity duplication |
| D5 | Assessment/grades prioritized after foundation; no hard-delete of grades |
| D6 | Incremental RLS design per domain; priority list in [06-RLS-SECURITY.md](./06-RLS-SECURITY.md) |

Mandatory conditions: additive migrations only; no destructive ops without approval; no unnecessary objects; evidence before indexes; progressive CHECKs; Laravel compatibility; gate + STOP every phase.

---

## 3. Architectural principles

1. **PostgreSQL is source of truth** — Redis is cache only.  
2. **Multi-schema bounded contexts** — one schema ≈ one domain responsibility.  
3. **Multi-school, multi-year** — `school_id` + `academic_year_id` on academic/operational facts.  
4. **No hard-delete** of official academic/financial/medical/audit history.  
5. **FK integrity in the database** — RESTRICT on critical history; no casual CASCADE.  
6. **Defense in depth** — Laravel policies + PostgreSQL RLS (incremental).  
7. **Intelligence cannot mutate SIS integrity** — recommendations + Tier-1 operational heal only.  
8. **Additive migrations only** after shared environments exist.  
9. **Measure before partition/index** — adaptive governance; attendance/grades are known P0 exceptions.  
10. **Laravel-compatible** — portable migrations + raw SQL for schemas/RLS/partitions/MVs.

---

## 4. Platform stack

```text
Laravel HTTP / Queues / Policies
        │
Application Handlers (transactions, outbox, idempotency)
        │
Infrastructure Eloquent Records
        │
PostgreSQL 18  ── schemas / RLS / partitions / MVs
        │
Intelligence (observe → recommend → human gate)
```

---

## 5. Institutional hierarchy

```text
ministries
  └── directorates
        └── schools
              ├── branches (campuses)
              ├── departments
              └── rooms (incl. workshop/lab room_type)
```

Multiple schools share one database and schema set. Isolation is by `school_id` + RLS + application school context — **not** by duplicating structures.

---

## 6. Identity strategy

### Current (preserve — D2)

| Concept | Storage |
|---------|---------|
| Login identity | `public.users` |
| RBAC | `security.roles`, `permissions`, `user_roles`, `scopes` |
| Student | `students.students` |
| Guardian | `guardians.guardians` |
| Teacher | `teachers.teachers` |

Admission (D4) links to students without cloning identity rows. A shared `persons` table remains optional and requires a future ADR.

---

## 7. PK / type conventions (D1)

| Concern | Convention |
|---------|------------|
| Internal PK | `BIGINT GENERATED ALWAYS AS IDENTITY` |
| External ID | `UUID public_id` where APIs need opaque IDs |
| Status / enums | `SMALLINT` |
| Money | `NUMERIC(12,2)` |
| Timestamps | `TIMESTAMPTZ` |
| Temporal history | `effective_from` / `effective_to` + `status` |
| Metadata | `JSONB` only for true flexible bags |

---

## 8. Normalization

Target **3NF**. Denormalize only with documented workload evidence (e.g. `daily_section_summary`, reporting MVs). Forbid CSV-of-IDs and relational data stuffed into JSONB.

---

## 9. Security classification

| Class | Examples | Controls |
|-------|----------|----------|
| PUBLIC | School name, published calendars | Standard |
| INTERNAL | Timetables, room lists | Auth + school scope |
| CONFIDENTIAL | Student PII, enrollments, attendance | RLS + RBAC + audit |
| RESTRICTED | Grades, finance, HR payroll | Stronger RBAC + audit + immutability rules |
| HIGHLY_RESTRICTED | Medical, SEN/IEP, counseling | Dedicated schema, RLS, least privilege, access audit |

---

## 10. Vocational specifics

Courses/subjects must represent theory, practical, laboratory, and workshop components. Workshops require capacity vs safety_capacity, batches, and block scheduling — designed in later phases without renaming live schemas.

---

## 11. ERP integration principle

Shared facts stay single-homed. ERP domains arrive only through phase gates (D3).

---

## 12. Intelligence / self-healing boundary

Allowed (Tier 1): pool resize, replica routing, observe/recommend.  
Forbidden auto: DROP/TRUNCATE academic tables, disable RLS, mutate grades/enrollments/finance/audit/medical, destructive migrations.

---

## 13. Documentation set

| Doc | Topic |
|-----|-------|
| 00 | This architecture |
| 01 | Schema catalog |
| 02 | Table catalog |
| 03 | Relationship map |
| 04 | Index strategy |
| 05 | Partitioning strategy |
| 06 | RLS security |
| 07 | Data lifecycle |
| 08 | Audit strategy |
| 09 | Migration strategy |
| 10 | Database governance |
| 11–14 | Performance, testing, ERP, intelligence (later phases as needed) |
| SIS-ERD.md | Machine-readable ERD of implemented schema (post-DDL phases) |
| MASTER-AUDIT / PHASE-*-GATE | Discovery + approvals |

---

## 14. Definition of done (database)

A phase is done only when migrations apply cleanly (if any), FKs/indexes/RLS match design, tests cover structure/security/lifecycle as applicable, blueprint is updated, and a phase gate report is issued — then **STOP** for human review.
