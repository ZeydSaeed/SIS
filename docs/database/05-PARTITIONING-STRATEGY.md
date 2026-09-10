# 05 — Partitioning Strategy

**Status:** Phase 1 design  
**Related:** ADR-002, ADR-020, blueprint partition table

---

## Principles

1. Partition only where scale justifies cost (planning, tooling, app awareness).  
2. Known P0 exceptions from capacity planning may be partitioned at first create.  
3. Never partition small reference tables.  
4. Partition automation must not DROP/DETACH in ways that destroy history without approval.

---

## Current live state

| Table | Strategy | Status |
|-------|----------|--------|
| `attendance.records` | LIST (`academic_year_id`) + `records_default` | **LIVE** |

---

## Planned (not Phase 1 DDL)

| Table | Key | Strategy | When |
|-------|-----|----------|------|
| `exams.student_grades` | `academic_year_id` | LIST | At first create (Assessment phase — D5) |
| `audit.audit_logs` | `created_at` | RANGE monthly | When audit_logs created |
| `communication.messages` | `created_at` | RANGE | When volume justifies |
| `finance.transactions` | `academic_year_id` | LIST | When fee/GL history grows |

---

## Operational rules

- Create new academic-year partitions via **additive migration** or approved automation before year start.  
- DEFAULT partition is a safety net — do not rely on it long-term for hot years.  
- Detach/archive requires human approval + retention plan ([07-DATA-LIFECYCLE.md](./07-DATA-LIFECYCLE.md)).  
- Intelligence must not auto-detach or drop partitions of critical SIS data.

---

## Validation

- Row counts stable across partition boundaries  
- Unique constraints include partition key where required (already true for attendance PK)  
- App queries always include `academic_year_id` when possible for partition pruning  
