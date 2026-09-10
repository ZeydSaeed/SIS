# SIS DATABASE — PHASE 3C.10 GATE REPORT

**Physical Schema Design & Database Hardening Blueprint**  
**Date:** 2026-09-10  
**Mode:** AUDIT + PHYSICAL DESIGN ONLY  

```text
PHASE 3C.10 STATUS: COMPLETE
PHYSICAL SCHEMA IMPLEMENTATION: NOT STARTED
DDL EXECUTION: NOT AUTHORIZED
MIGRATION CREATION: NOT AUTHORIZED
HUMAN APPROVAL: REQUIRED
```

---

## 1. Executive Summary

Phase 3C.10 converts the approved Phase 3C.9 logical Completion/Graduation model into an implementation-ready PostgreSQL physical blueprint under reserved schema `graduation`. Design reuses LIVE conventions (IDENTITY PKs, composite `(enrollment_id, school_id)` FKs, FAIL-CLOSED RLS, `audit.outbox_messages`, `audit.idempotency_keys`), preserves Completion ≠ Graduation ≠ Award ≠ StudentStatus projection, and explicitly refuses partitioning at launch.

**No DDL, migrations, or application code were created or executed.**

---

## 2. Scope

**In scope:** Documentation-only physical design for Completion/Graduation entities from 3C.9.  

**Out of scope:** Migrations, DDL, RLS SQL execution, triggers, app handlers, policy content, role inventories, GPA gates, Results table implementation.

**Artifacts:** `.cursor/database/phase-3c-10/*`

---

## 3. Predecessor Verification

| Predecessor | Status | Used as |
|-------------|--------|---------|
| 3A Exam Foundation | Authoritative | Evidence source refs; composite FK patterns |
| 3B student_grades | Authoritative SSOT grades | Do not duplicate |
| 3B.1 Grade immutability | Pattern | Append-only / VOID semantics inspiration |
| 3C Results Architecture | Consumer context | Results ≠ graduation SSOT |
| 3C.7 Graduation Architecture | Authority | Concept separation |
| 3C.8 / 3C.8A / 3C.8B | Locked HDs/DLs | HD-19,20,21,22,31,32,35,36,39; DL-017…022 |
| 3C.9 Logical Schema | **Immediate authority** | Entity/grain/SSOT mapping |

No silent override of 3C.9. Logical↔physical tensions recorded in Decision Register (not silently resolved).

---

## 4. Existing Database Inventory

| Concern | LIVE finding |
|---------|--------------|
| Schemas | Includes exams, enrollment, audit, reserved empty `graduation` |
| PK | `BIGINT GENERATED ALWAYS AS IDENTITY` |
| Tenancy | Explicit `school_id` + RLS GUC `app.current_school_id` (fail-closed) |
| Composite FK | Grades: `(enrollment_id, school_id)` → enrollments |
| Soft-delete official | Forbidden for grades; status/version patterns |
| Partition | Grades by academic year; attendance partitioned — not template for all tables |
| Outbox / idempotency | `audit.outbox_messages`, `audit.idempotency_keys` |
| Intelligence tables | Separate — not graduation SSOT |
| Blueprint `graduation.*` sketches | **STALE** — do not implement as-is |

---

## 5. Logical → Physical Mapping

See `PHYSICAL-SCHEMA-DESIGN.md` §4. Fourteen physical tables in `graduation`; StudentStatus = projection (no SSOT table); Transcript = consumer only.

---

## 6. Table Catalog Summary

14 tables cataloged in `TABLE-CATALOG.md`. All school-scoped academic facts: RLS YES; official rows NEVER hard-delete; no launch partitioning.

---

## 7. Column Strategy

- TIMESTAMPTZ; SMALLINT statuses; explicit states over overloaded NULL  
- Denorm `student_id` / `academic_year_id` / `specialization_id` on outcome/award roots with integrity requirement  
- No mandatory GPA column (HD-22)  
- Actor columns opaque BIGINT (HD-31 — no invented roles)  
- JSONB only for extensible policy/requirement payloads (content not invented)  
- Details: `COLUMN-CATALOG.md`

---

## 8. PK / FK Strategy

- **PK:** BIGINT IDENTITY + natural UNIQUE for domain identity  
- **FK:** RESTRICT everywhere on historical academic links; **no CASCADE**  
- **Composite:** `(enrollment_id, school_id)` mandatory for enrollment-scoped roots  

---

## 9. Constraint Strategy

UNIQUE grain, version monotonicity, partial UNIQUE current official/issued, CHECKs for exclusion-reason/self-supersession/decided fields. Optional policy effective-range EXCLUSION deferred (D-3C10-002). Catalog: `CONSTRAINT-CATALOG.md`.

---

## 10. Versioning Strategy

Stable entity + version rows + `version_no`; effective dating on policies; recorded time `created_at`; domain event timestamps separate. See `VERSION-LINEAGE-DESIGN.md`.

---

## 11. Immutability Strategy

Combination: append-only versions + partial UNIQUE + BEFORE UPDATE/DELETE triggers + privilege separation + app guards. App-only rejected for official records. See `IMMUTABILITY-DESIGN.md`.

---

## 12. Supersession Strategy

Hybrid self-refs + `outcome_supersessions` append-only edges; cycle/self-super prevention; HD-35 workflow preserved.

---

## 13. Revocation Strategy

`revocation_records` append-only; award version status → revoked; never DELETE; HD-36 lineage preserved; reason/role codes opaque until decided.

---

## 14–15. Provenance / Evidence

Typed evidence references only; fingerprints optional complement (not idempotency); definitions ≠ evaluations ≠ evidence ≠ outcomes. See `PROVENANCE-DESIGN.md`.

---

## 16. Completion Physical Model

`completion_outcomes` + `completion_outcome_versions` + evaluations + evidence. Grain `(school_id, enrollment_id)`.

---

## 17. Graduation Approval Physical Model

`graduation_approvals` attempts on completion versions; decided rows immutable; does not mutate completion evidence.

---

## 18. Graduation Award Physical Model

`graduation_awards` + `graduation_award_versions`; issued immutable; revoke/supersede only.

---

## 19. Projection Strategy

```text
source_of_truth: graduation_award_versions (current issued, non-revoked) + completion official versions
projection: StudentStatus / caches — NOT SSOT
rebuild_strategy: outbox-driven rebuild job
consistency_model: eventual via outbox
lag_tolerance: HUMAN/ops decision — not invented SLA
```

No projection table in this blueprint without D-3C10-008 approval.

---

## 20. RLS Design

ENABLE+FORCE on all school-scoped graduation tables; fail-closed GUC; DELETE denied for official/append-only. See `RLS-DESIGN.md`.

---

## 21. Cross-School Integrity

Composite FKs + denorm school_id + RLS. Independent enrollment FK alone forbidden.

---

## 22–23. Index / Partial / Covering

Query-driven indexes; partial UNIQUE for current versions; INCLUDE deferred until measured. Redundant risks documented. `INDEX-CATALOG.md`.

---

## 24. Partitioning Assessment

**NO PARTITION** at launch. Evidence_items watchlist only. `PARTITIONING-DESIGN.md`.

---

## 25. 20-Year Scale Model

Assumptions explicit; evidence_items ~O(10M) rows / single-digit GB with indexes at baseline — partitioning still not mandatory. `SCALE-MODEL.md`.

---

## 26. Query Matrix

14 critical questions mapped to indexes. `QUERY-MATRIX.md`.

---

## 27. Historical Reconstruction Strategy

Conceptual join plan from enrollment → versions → evaluations → evidence → policy → approval → award → supersession/revocation. Executable SQL not run.

---

## 28. Concurrency

| Operation | Strategy |
|-----------|----------|
| Concurrent version create | UNIQUE (parent, version_no); serialize version_no allocation in txn |
| Concurrent current official | Partial UNIQUE + single txn flip |
| Concurrent approval | UNIQUE (version, attempt_no) + idempotency key |
| Award issuance | Partial UNIQUE current issued + idempotency |
| Revocation | Append revoke + conditional update status |

---

## 29. Idempotency

Reuse **`audit.idempotency_keys`** only.

| Operation | Scope key sketch | Replay |
|-----------|------------------|--------|
| Publish completion official | school + enrollment + operation + client key | Return existing version |
| Approval decide | school + approval attempt + key | No double decide |
| Award issue | school + enrollment + approval_id + key | Return existing award version |
| Revoke | school + award_version + key | Return existing revoke |

Retention: follow existing audit idempotency retention — do not invent parallel store.

---

## 30. Outbox

Reuse **`audit.outbox_messages`**.

| Event | Status |
|-------|--------|
| COMPLETION_EVALUATED | PROPOSED — EVENT GOVERNANCE |
| COMPLETION_OFFICIALLY_RECORDED | PROPOSED |
| GRADUATION_APPROVED | PROPOSED |
| GRADUATION_AWARD_ISSUED | PROPOSED |
| GRADUATION_AWARD_REVOKED | PROPOSED |
| VERSION_SUPERSEDED / VERSION_REVOKED | PROPOSED |

---

## 31. Audit

Map mutations to existing security/audit pipeline (who/what/when/why/record/version/correlation). No second audit SSOT.

---

## 32. Retention / Archival

Official completion, approvals, awards, evidence, evaluations, lineage: **permanent academic retention**. Archival = cold storage / partition later — not hard-delete. Audit retention per existing audit policy.

---

## 33. Migration Dependency Plan

Ordered 1→20 in `MIGRATION-ORDER.md`. **Plan only.**

---

## 34. Rollback Plan

Empty-object create rollback possible with approval; data-bearing official rows academically irreversible; DISABLE RLS never automatic. Zero-destructive automatic DROP forbidden.

---

## 35. Negative Test Design (design only — not executed)

1. Cross-school enrollment reference → FK/RLS fail  
2. Duplicate active official version → partial UNIQUE fail  
3. Supersession cycle → reject  
4. Self-supersession → CHECK fail  
5. Duplicate award identity / current issued → UNIQUE fail  
6. Mutate immutable official → trigger/privilege fail  
7. DELETE official → RLS/privilege fail  
8. Revoked row marked current issued → CHECK/partial UNIQUE fail  
9. Invalid temporal interval → CHECK/exclusion (if adopted)  
10. Concurrent version create → one wins UNIQUE  
11. Concurrent approval → UNIQUE/idempotency  
12. Duplicate idempotency key → replay safe  
13. RLS tenant escape → fail-closed deny  
14. Projection drift → rebuild from SSOT  
15. Reconstruct after supersession → lineage complete  
16. Reconstruct after revocation → award + revoke retained  

---

## 36. Open Decisions

`DECISION-REGISTER.md` D-3C10-001…008 + predecessor open HDs (content/roles/award attrs). **Not silently closed.**

---

## 37. Blocking Findings

| Finding | Severity |
|---------|----------|
| None that block **design-phase** completion | — |
| Event taxonomy unapproved | Condition for later implementation |
| Policy JSONB content empty until HD-20/21 authored | Condition |
| Projection table not authorized | Condition |
| Stale blueprint names must not be implemented | Condition |

No undocumented cross-school FK path in the design. No DDL executed.

---

## 38. Remediation Plan

Before Phase 3C.11 / any migration:

1. Human close D-3C10-001…008 as needed  
2. Event governance approve outbox names  
3. Confirm enrollments UNIQUE `(id, school_id)` LIVE  
4. Align RLS SQL text to fail-closed Phase 3B pattern  
5. Update database-blueprint only after implementation authorization  

---

## 39. Final Score

| Area | Weight | Score | Notes |
|------|-------:|------:|-------|
| Logical-to-physical correctness | 15 | 15 | 3C.9 mapped; separations preserved |
| Identity / tenancy | 10 | 10 | school+enrollment; composite FKs |
| Constraints / integrity | 10 | 9 | Exclusion deferred openly |
| Versioning / immutability | 10 | 10 | Hybrid lineage + enforcement stack |
| Provenance / reconstruction | 10 | 10 | Evidence refs + query |
| RLS / security | 10 | 9 | Designed; FK/RLS caveat documented |
| Indexing / query performance | 10 | 9 | Query-mapped; covering deferred |
| Partitioning / scale | 5 | 5 | No unjustified partition; 20y model |
| Concurrency / idempotency | 5 | 5 | Reuse audit keys |
| Audit / outbox | 5 | 4 | Events PROPOSED pending governance |
| Migration / rollback design | 5 | 5 | Ordered plan |
| Documentation / decision discipline | 5 | 5 | Register + catalogs |
| **TOTAL** | **100** | **96** | |

```text
90–100 = PASS
```

**PASS WITH CONDITIONS** (open physical decisions + event governance + policy content still open from predecessors).

---

## 40. Final Gate

```text
PHASE 3C.10: DESIGN COMPLETE

PHYSICAL SCHEMA: DESIGNED
DDL: NOT EXECUTED
MIGRATIONS: NOT CREATED
IMPLEMENTATION: NOT STARTED
HUMAN APPROVAL: REQUIRED

NEXT POSSIBLE PHASE:
PHASE 3C.11 — IMPLEMENTATION PLANNING

IMPLEMENTATION AUTHORIZATION:
NOT GRANTED
```

### Absolute execution boundary verification

```text
MIGRATIONS CREATED: NO
TABLES CREATED: NO
DDL EXECUTED: NO
INDEXES CREATED: NO
RLS POLICIES CREATED: NO
TRIGGERS CREATED: NO
APPLICATION CODE MODIFIED: NO
BUSINESS LOGIC MODIFIED: NO
DATA MODIFIED: NO
```

### Gate result

```text
FINAL GATE STATUS: PASS WITH CONDITIONS
SCORE: 96 / 100
PHYSICAL SCHEMA IMPLEMENTATION: NOT STARTED
DDL EXECUTION: NOT AUTHORIZED
MIGRATION CREATION: NOT AUTHORIZED
HUMAN APPROVAL: REQUIRED
```

---

## Architectural Fitness Checklist

| Check | Result |
|-------|--------|
| Identity unambiguous | PASS |
| Cross-school FK loopholes | PASS (composite designed) |
| SSOT separation | PASS |
| Completion ≠ Graduation ≠ Award ≠ projection | PASS |
| Immutable official + lineage + revoke | PASS (design) |
| Provenance reconstructable | PASS (design) |
| RLS coverage school-scoped | PASS (design) |
| Critical queries indexed | PASS |
| Partitioning justified | PASS (none) |
| Audit/outbox reused | PASS |
| No business policy invented | PASS |
| No DDL/migrations/app changes | PASS |
