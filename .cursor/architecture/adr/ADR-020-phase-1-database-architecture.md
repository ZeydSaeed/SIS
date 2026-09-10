# ADR-020: Phase 1 Database Architecture Decision Lock

**Status:** Accepted  
**Date:** 2026-09-10  
**Phase:** Database Phase 1  
**Supersedes:** Nothing — reinforces ADR-003, ADR-005, and Phase 0 gate conditions  
**Human approval:** `APPROVED PHASE 1` with Decisions D1–D6

## Decision

The following decisions are **binding** for all subsequent database phases until a new ADR + human approval supersedes them.

### D1 — Primary Keys

- Internal PKs: **`BIGINT GENERATED ALWAYS AS IDENTITY`** project-wide.
- Do **not** migrate existing IDs to UUID.
- Optional `UUID public_id` remains allowed for external/API identifiers only.
- New domains must follow BIGINT IDENTITY unless a future documented exception is approved.

### D2 — Existing Schemas

- Preserve existing schema names and structures where architecturally valid.
- Do **not** rename/recreate schemas merely for naming consistency with external prompts.
- `organization.branches` is the campus/branch concept.
- Structural change to an existing schema requires documentation + migration planning + approval.

### D3 — Phased Implementation

- Do **not** create the complete Educational ERP in one migration wave.
- Implement domain-by-domain with **mandatory phase gates**.
- Each phase: migrate → test → audit → document → gate → human review → next.

### D4 — Admission

- Dedicated `admission` schema will be created in the **appropriate implementation phase** (Phase 2+), not as undocumented drive-by DDL.
- Must integrate with existing `students` / `enrollment` without duplicating student identity.

### D5 — Assessment / Grades / Exams

- Prioritize Assessment after foundation completion.
- Include exams, grade structures/items, grades, grade history, results, report cards/transcripts as appropriate.
- Grades are **CRITICAL** — no destructive hard delete; corrections must remain auditable.

### D6 — RLS Expansion

- Expand PostgreSQL RLS **incrementally** with explicit design + validation per domain.
- Do **not** enable RLS blindly on every table.
- Priority review list: students, enrollments, attendance, assessment/grades, staff/HR, finance, medical, behavior, documents, support/SEN, school-scoped inventory, school-scoped communications.

### Mandatory conditions (always)

1. Do not modify applied migration history — additive migrations only.  
2. No destructive DB ops without explicit human approval.  
3. No DROP TABLE/COLUMN/FK, TRUNCATE, RLS disable, or destructive CASCADE on critical SIS data.  
4. No unnecessary schemas/tables/indexes.  
5. Analyze existing indexes/FK/query patterns before adding indexes.  
6. Introduce CHECK constraints progressively with data-compatibility validation.  
7. Preserve Laravel application backward compatibility.  
8. Every phase produces a Gate Report and STOPs for human approval.

## Why

Phase 0 found a solid foundation with large blueprint/ERP gaps. Binding decisions prevent PK fragmentation, schema rename churn, big-bang ERP migrations, and unsafe RLS/CHECK rollouts.

## Alternatives Considered

| Alternative | Rejected Because |
|-------------|------------------|
| UUID internal PKs for new domains | Mixed PK types forever; conflicts ADR-003 |
| Rename schemas to master-prompt names | Breaks live DB + Laravel without benefit |
| Single ERP migration wave | Unreviewable risk; violates D3 |
| Enable RLS on all tables immediately | Can break workers/seeders; needs per-domain design |

## Consequences

- Phase 1 delivers **documentation/ADR only** — no DDL.
- Phase 2+ must cite ADR-020 in gate reports when touching PK/schema/RLS/assessment/admission.
- Blueprint academic object count SSOT reconciled to **87** (see database-blueprint.md); intelligence remains separate.

## Review condition

Re-open only with human approval + new ADR if: sharding requires different IDs, schema merge/rename is mandatory, or RLS model changes fundamentally.

## Related

- [ADR-003](./ADR-003-bigint-identity.md)
- [ADR-005](./ADR-005-rls.md)
- [docs/database/SIS-DATABASE-PHASE-1-PLAN.md](../../../docs/database/SIS-DATABASE-PHASE-1-PLAN.md)
- [docs/database/SIS-DATABASE-PHASE-0-GATE.md](../../../docs/database/SIS-DATABASE-PHASE-0-GATE.md)
- [database-blueprint.md](../database-blueprint.md)
