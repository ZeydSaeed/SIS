# SIS DATABASE — PHASE 3C.10  
# PHYSICAL SCHEMA DESIGN (BLUEPRINT ONLY)

**Date:** 2026-09-10  
**Mode:** AUDIT + PHYSICAL DESIGN ONLY  

```text
DDL EXECUTION: NOT AUTHORIZED
MIGRATION CREATION: NOT AUTHORIZED
PHYSICAL SCHEMA IMPLEMENTATION: NOT STARTED
HUMAN APPROVAL: REQUIRED
```

---

## 1. Immediate Logical Authority

Phase **3C.9** logical model is authoritative. This phase maps it to PostgreSQL **without** reopening HD/DL locks from 3C.8B.

---

## 2. Existing Database Inventory (relevant)

| Area | Finding |
|------|---------|
| Reserved schema `graduation` | Empty (SchemaHelper); blueprint sketches **STALE** — do not implement blueprint tables as-is |
| `enrollment.enrollments` | LIVE: `id`, `school_id`, `student_id`, `academic_year_id`, `specialization_id`, … |
| Composite tenant pattern | LIVE grades: UNIQUE `(id, school_id)` parents + composite FKs `(enrollment_id, school_id)` |
| PK convention | `BIGINT GENERATED ALWAYS AS IDENTITY` (grades/attendance) |
| RLS | ENABLE + FORCE; `app.current_school_id` GUC; fail-closed |
| Outbox | `audit.outbox_messages` — **reuse** |
| Idempotency | `audit.idempotency_keys` — **reuse** |
| Soft-delete of official grades | Forbidden; VOID+INSERT / status |
| Partitioning | Grades LIST by `academic_year_id`; attendance partitioned — **not default for graduation** |
| Timestamps | `TIMESTAMPTZ` |
| Status | `SMALLINT` |
| Money | N/A |

---

## 3. Physical Schema Choice

**Schema:** `graduation` (reuse reserved empty schema)

Houses both Completion and Graduation physical tables (module packaging). Naming prefixes distinguish concepts (`completion_*` vs `graduation_*`).

**Do not** create competing `results.graduation_*` SSOT.

---

## 4. Logical → Physical Mapping

| Logical (3C.9) | Physical table | Notes |
|----------------|----------------|-------|
| EligibilityPolicy | `graduation.eligibility_policies` | school-scoped family |
| EligibilityPolicyVersion | `graduation.eligibility_policy_versions` | immutable when published |
| RequirementDefinition | `graduation.requirement_definitions` | |
| RequirementDefinitionVersion | `graduation.requirement_definition_versions` | rule shell; JSONB optional for variable rule payload **without** inventing values |
| CompletionOutcome | `graduation.completion_outcomes` | UNIQUE `(school_id, enrollment_id)` |
| CompletionOutcomeVersion | `graduation.completion_outcome_versions` | monotonic `version_no` |
| RequirementEvaluation | `graduation.requirement_evaluations` | |
| EvidenceSet | `graduation.evidence_sets` | 1:1 with completion version |
| EvidenceItem | `graduation.evidence_items` | typed source refs |
| GraduationApproval | `graduation.graduation_approvals` | |
| GraduationAward | `graduation.graduation_awards` | UNIQUE `(school_id, enrollment_id)` |
| GraduationAwardVersion | `graduation.graduation_award_versions` | |
| OutcomeSupersession | `graduation.outcome_supersessions` | typed lineage |
| RevocationRecord | `graduation.revocation_records` | |
| StudentStatus projection | **no table** | sync via outbox → students module |
| Transcript | **no duplicate** | consumer pins (3C.6) |

---

## 5. Identity / Tenancy

| Table class | Scope | `school_id` |
|-------------|-------|-------------|
| Policies / outcomes / awards / approvals / revocations | school-scoped | **Explicit** + composite FK to enrollment |
| Evidence children | indirect | Via parent version; optional denorm school_id for RLS simplicity **RECOMMENDED** denorm on evaluations/items |

**Business identity preserved:** `(school_id, enrollment_id)` for outcomes/awards — surrogate `id` for FK locality, **not** replacing natural uniqueness.

**Denorm (controlled):** `student_id`, `academic_year_id`, `specialization_id` on outcome/award roots — must match enrollment (CHECK trigger or composite validation) — **PHYSICAL DESIGN REQUIREMENT**.

---

## 6. PK Strategy

| Choice | Rationale |
|--------|-----------|
| `BIGINT GENERATED ALWAYS AS IDENTITY` | Aligns LIVE grades/attendance; partition-friendly; compact FKs |
| UUID | Not selected for these tables (external exposure via opaque IDs at API layer later) |

Surrogate PK + natural UNIQUE `(school_id, enrollment_id)` / `(parent_id, version_no)`.

---

## 7. FK / ON DELETE

**Default:** `ON DELETE RESTRICT` / `ON UPDATE RESTRICT` for all historical academic links.

**Never CASCADE** official completion/award/evidence/approval rows.

Parents that may need unique `(id, school_id)` for composite FKs:

```text
PHYSICAL DESIGN REQUIREMENT:
Ensure UNIQUE (id, school_id) on enrollment.enrollments if not already present
(Phase 3A/3B already creates enrollments_id_school_id_unique for PG)
```

---

## 8. Versioning / Immutability / Supersession / Revocation

See companion: `IMMUTABILITY-DESIGN.md`, `VERSION-LINEAGE-DESIGN.md`.

Summary:

- Official completion/award versions: append-only; UPDATE blocked after officialization  
- Partial UNIQUE: one `is_current_official` completion version per outcome; one current non-revoked award version per award  
- Supersession via `outcome_supersessions` + columns `supersedes_version_id` / `superseded_by_version_id`  
- Revocation via `revocation_records` — never DELETE award  

---

## 9. Partitioning Assessment

| Table | Recommendation |
|-------|----------------|
| completion_outcome_versions, requirement_evaluations, evidence_items | **No partition initially** — volume ≪ grades; revisit with evidence |
| awards / approvals / policies | **No partition** |
| Future trigger | Academic-year LIST only if measured growth + pruning benefit outweighs FK complexity |

```text
ASSUMPTION: ≤ tens of versions per enrollment × 45K students × years ≪ grade partition need
```

---

## 10. RLS

All school-scoped graduation tables: **ENABLE + FORCE RLS**, policy pattern mirror `student_grades_school_isolation` on `school_id` vs `app.current_school_id`.

DELETE: deny for immutable official tables (policy or revoke privilege).

---

## 11. PostgreSQL-Specific Features

| Feature | Use | Portability |
|---------|-----|-------------|
| GENERATED ALWAYS AS IDENTITY | PK | PG-SPECIFIC (acceptable) |
| Partial UNIQUE indexes | current official version | PG-SPECIFIC |
| INCLUDE indexes | selective covering | PG-SPECIFIC |
| RLS + FORCE | tenancy | PG-SPECIFIC |
| Exclusion constraints | optional temporal non-overlap on policy effective ranges | PG-SPECIFIC — **HUMAN DECISION** if needed |
| JSONB | optional rule_payload on requirement versions | PG-SPECIFIC |
| Triggers | immutability guards | PG-SPECIFIC |

---

## 12. Open Physical Decisions (not silently closed)

See `DECISION-REGISTER.md` — e.g. exact SMALLINT status enums, exclusion vs CHECK for policy dates, whether evidence_sets is separate table vs embed.

---

## 13. Companion Catalogs

All under `.cursor/database/phase-3c-10/`:

| Artifact | Purpose |
|----------|---------|
| `TABLE-CATALOG.md` | Table inventory |
| `COLUMN-CATALOG.md` | Core column definitions |
| `CONSTRAINT-CATALOG.md` | PK/FK/UNIQUE/CHECK/partial |
| `INDEX-CATALOG.md` | Indexes + redundancy risks |
| `RLS-DESIGN.md` | RLS design (not executed) |
| `PARTITIONING-DESIGN.md` | No-partition assessment |
| `IMMUTABILITY-DESIGN.md` | Enforcement stack |
| `VERSION-LINEAGE-DESIGN.md` | Supersession / revocation |
| `PROVENANCE-DESIGN.md` | Evidence & fingerprints |
| `MIGRATION-ORDER.md` | Dependency plan only |
| `QUERY-MATRIX.md` | Critical queries |
| `SCALE-MODEL.md` | 1–20 year assumptions |
| `DECISION-REGISTER.md` | Open human decisions |
| `PHASE-3C-10-GATE-REPORT.md` | Gate score & status |

## 14. Concurrency / Idempotency / Outbox (summary)

- Concurrency: UNIQUE version + partial UNIQUE current + txn flip  
- Idempotency: **`audit.idempotency_keys` only**  
- Outbox: **`audit.outbox_messages`**; event names PROPOSED (see Decision Register)

## 15. Design completion marker

```text
PHASE 3C.10: DESIGN COMPLETE
PHYSICAL SCHEMA: DESIGNED
DDL: NOT EXECUTED
MIGRATIONS: NOT CREATED
IMPLEMENTATION: NOT STARTED
HUMAN APPROVAL: REQUIRED
```
