# PHASE 3C.10A — PHYSICAL DESIGN FINALIZATION

**Date:** 2026-09-10  
**Mode:** Audit + decision closure only  
**Authority:** Phase 3C.10 design (unchanged unless noted)  

```text
DDL: NOT EXECUTED
MIGRATIONS: NOT CREATED
IMPLEMENTATION: NOT STARTED
```

**Existing 3C.10 documents:** Not rewritten. Closure statuses live in `DECISION-CLOSURE-MATRIX.md`. No contradiction requiring design rewrite was found.

---

## 1. Physical design status

```text
PHYSICAL DESIGN: FINAL
```

Table boundaries, identities, FKs, RLS boundaries, immutability/lineage strategy, event **storage**, projection decision, indexes (REQUIRED set), and launch partition strategy are stable enough for **Phase 3C.11 — Implementation Planning**.

Business-policy content (rules, roles, award catalogs, date semantics) remains deferred and is accommodated by nullable/opaque/extensible columns without redesign.

---

## 2. Schema name (D-3C10-001) — CLOSED

```text
FINAL RECOMMENDATION: graduation
RATIONALE: Reserved in SchemaHelper::schemas(); Phase 0 empty reserved schema; 3C.9 ownership; blueprint namespace (stale tables ignored).
STATUS: CLOSED
```

Do **not** implement blueprint `graduation.eligibility_rules` / `graduation.records`.

---

## 3. Temporal / exclusion finalization (D-3C10-002)

```text
NO EXCLUSION REQUIRED
```

Overlap of two concurrently effective policy versions: **FORBIDDEN** (domain). Enforcement: partial UNIQUE / current-effective flag + publish transaction — not GiST EXCLUSION at launch.

---

## 4. Event / outbox finalization

| Concern | Decision |
|---------|----------|
| Storage | **CLOSED** — `audit.outbox_messages` only |
| Second outbox | **FORBIDDEN** |
| Event names | **DEFERRED WITH SAFE DEFAULT** — 3C.9 dotted PROPOSED placeholders |
| Payload business thresholds | **FORBIDDEN** to invent |

Event governance ≠ physical storage. 3C.11 may plan staging calls; production consumer contracts wait for governance.

---

## 5. Projection finalization (D-3C10-008) — CLOSED

- **No dedicated graduation projection table**  
- **Existing** `students` + `StudentStatus::Graduated` is the sync target  
- SSOT = award (and completion for completion state)  
- Rebuild from SSOT via outbox; lag SLA not invented  

---

## 6. Revocation finalization

```text
REVOCATION ≠ DELETION — PROVEN (design)
```

| Element | Preserved? |
|---------|------------|
| Original award version row | YES — never hard-deleted |
| revocation_records append | YES — actor, revoked_at, reason_ref, correlation |
| lineage / supersession | YES — separate edges |
| current-state derivation | `is_current_issued=false` + lifecycle revoked; new version only after new approval |

**Mutable-state contradiction:** None found. Status flags on award version are controlled lineage updates, not payload rewrite. Historical reconstruction retains issued row + revoke event.

**BLOCKING FINDING:** None.

---

## 7. Denormalized identity integrity

| Combination | Protection (design — not executed) |
|-------------|-------------------------------------|
| `(enrollment_id, school_id)` on outcomes/awards/approvals | **Composite FK** → `enrollments(id, school_id)` RESTRICT |
| `school_id` alone on all school-scoped tables | RLS fail-closed + NOT NULL |
| `student_id`, `academic_year_id`, `specialization_id` denorm | Must match enrollment: **REQUIRED** BEFORE INSERT/UPDATE trigger (or composite unique parent if LIVE supports) — application-only **rejected** |
| Child `school_id` denorm on evaluations/evidence | Must equal parent version `school_id` — trigger or composite FK through parent |

Invalid cross-school enrollment pairing cannot satisfy composite FK. Invalid student/year denorm cannot rely on app alone — DB guard required at implementation.

**BLOCKING FINDING:** None for design; implementation planning must include denorm-consistency triggers.

---

## 8. BIGINT + domain identity

| Root | Technical PK | Domain identity | Why both | API exposure |
|------|--------------|-----------------|----------|--------------|
| completion_outcomes | `id` IDENTITY | UNIQUE `(school_id, enrollment_id)` | Compact FKs + preserve HD-39 grain | Prefer domain keys externally; surrogate internal |
| graduation_awards | `id` IDENTITY | UNIQUE `(school_id, enrollment_id)` | Same | Same |
| eligibility_policies | `id` | UNIQUE `(school_id, policy_code)` | Same | policy_code + school |
| *_versions | `id` | UNIQUE `(parent_id, version_no)` | Lineage + FK locality | version_no with parent |
| graduation_approvals | `id` | UNIQUE `(completion_outcome_version_id, attempt_no)` | Attempts | attempt scoped |
| evidence_sets | `id` | UNIQUE `(completion_outcome_version_id)` | 1:1 | via version |
| revocation_records / supersessions | `id` | Natural event uniqueness via FKs + time/correlation | Append-only events | event id ok |

Never treat technical PK as substitute for domain uniqueness in business rules.

---

## 9. Immutability final audit

| Entity | INSERT | UPDATE | DELETE | Revocation | Supersession |
|--------|--------|--------|--------|------------|--------------|
| eligibility_policy_versions (published) | YES | DENY payload | DENY | N/A | New version |
| requirement_definition_versions (published) | YES | DENY payload | DENY | N/A | New version |
| completion_outcome_versions (official) | YES | lineage flags only | DENY | N/A | New version + edge |
| requirement_evaluations (official parent) | YES w/ version | DENY | DENY | N/A | Via parent supersede |
| evidence_* (official parent) | YES w/ version | DENY | DENY | N/A | Via parent |
| graduation_approvals (decided) | YES | DENY | DENY | N/A | New attempt |
| graduation_award_versions (issued) | YES | flags only | DENY | Append revoke record | New version + edge |
| outcome_supersessions | YES | DENY | DENY | N/A | Is lineage |
| revocation_records | YES | DENY | DENY | Is revoke | N/A |

**Enforcement class:** combination — append-only model + partial UNIQUE + DB triggers + privilege revoke DELETE + application guards.  
**App-only:** insufficient for official rows — confirmed.

No triggers created in this phase.

---

## 10. Version lineage final audit (conceptual)

| Scenario | Expected |
|----------|----------|
| Self-supersession | CHECK rejects |
| Cycles | Controlled path + reject walk |
| Duplicate successors / multiple current | Partial UNIQUE rejects |
| Revoked still current issued | CHECK/partial UNIQUE rejects |
| Historical reconstruction | Versions + edges + revoke rows retained |

---

## 11. RLS final audit

All 14 school-scoped graduation tables: **RLS REQUIRED + FORCE REQUIRED**; tenant `app.current_school_id` fail-closed (match Phase 3B grades pattern). DELETE deny for official/append-only.

| Gap? | Result |
|------|--------|
| Unclear tenant boundary | **NONE** — BLOCKING not raised |

Policies not created.

---

## 12. Index final audit

| Index | Classification |
|-------|----------------|
| All PRIMARY KEYs | REQUIRED |
| uq_co_school_enroll / uq_ga_school_enroll | REQUIRED (domain) |
| uq_*_version (parent, version_no) | REQUIRED |
| uq_cov_current_official / uq_gav_current | REQUIRED (partial) |
| uq_approval_attempt | REQUIRED |
| uq_evidence_source / idx_ei_set_source | REQUIRED |
| idx_co_school_year | RECOMMENDED |
| idx_co_student | RECOMMENDED |
| idx_cov_school_status | RECOMMENDED |
| idx_cov_policy | RECOMMENDED |
| idx_re_version | REQUIRED (Q2/Q3) |
| idx_re_reqver | DEFERRED UNTIL TELEMETRY |
| idx_ei_source_lookup | RECOMMENDED (impact) |
| idx_ga_school_year | RECOMMENDED |
| idx_gav_awarded_at | RECOMMENDED |
| idx_approval_school_status | RECOMMENDED |
| idx_approval_version | REQUIRED |
| idx_rev_award_ver | REQUIRED |
| idx_super_pred | RECOMMENDED |
| idx_policy_school (extra BTREE) | **REDUNDANT / REMOVE FROM DESIGN** if UNIQUE `(school_id, policy_code)` exists |
| idx_polver_effective | RECOMMENDED |
| INCLUDE covering variants | DEFERRED UNTIL TELEMETRY |

No indexes created.

---

## 13. Partitioning final audit

```text
NO PARTITION AT LAUNCH
```

| Candidate | Verdict |
|-----------|---------|
| evidence_items | **DEFERRED / WATCHLIST** |
| requirement_evaluations | **DEFERRED / WATCHLIST** |
| All others | No partition |

Not forbidden forever — revisit with measured volume.

---

## 14. Historical reconstruction proof (design)

Chain for enrollment X / state Y / time Z:

```text
enrollment → completion_outcomes
  → completion_outcome_versions (≤ Z)
  → eligibility_policy_versions (pinned)
  → requirement_definition_versions (via evaluations)
  → requirement_evaluations
  → evidence_sets → evidence_items → upstream grade/result SSOTs by ref
  → graduation_approvals
  → graduation_award_versions
  → outcome_supersessions
  → revocation_records
  → actors + timestamps + correlation_id
```

All links present in 3C.10 model. **BLOCKING FINDING:** None.

---

## 15. Concurrency final audit

| Race | Physical invariant |
|------|-------------------|
| Version allocation | UNIQUE (parent, version_no) in txn |
| Official publish | Partial UNIQUE current official |
| Approval | UNIQUE (version, attempt) + idempotency |
| Award issue | Partial UNIQUE current issued + idempotency |
| Revocation | Append + conditional clear current |

---

## 16. Idempotency / outbox

| Store | Status |
|-------|--------|
| `audit.idempotency_keys` | **ONLY** — CLOSED |
| `audit.outbox_messages` | **ONLY** — CLOSED |

Scope: `(key, command_name)` per LIVE PK. Replay returns stored response_payload.

---

## 17. No-business-policy audit

| Topic | Invented? |
|-------|-----------|
| GPA / passing / credits / thresholds | NO — HD-22; nullable evidence only |
| Award types / honors values | NO — optional codes POLICY INPUT |
| Approval roles | NO — opaque actors |
| Retention years / SLA | NO — not specified as numbers |
| Event payload thresholds | NO |
| Unit type lists | NO — SMALLINT extensible shell only |

Anything still open marked HUMAN DECISION / POLICY INPUT in predecessor HDs — not silently closed as content.

---

## 18. Implementation readiness

```text
READY FOR IMPLEMENTATION PLANNING
```

Stable: tables, identity, FKs, RLS boundaries, immutability, lineage, event storage, projection decision, REQUIRED indexes, no-partition launch.

Not claimed: production engine authorization, policy content, role catalogs, event consumer contracts, DDL authorization.

```text
IMPLEMENTATION AUTHORIZATION: NOT GRANTED
```

---

## 19. Document change log (3C.10A)

| File | Change |
|------|--------|
| `DECISION-CLOSURE-MATRIX.md` | **ADDED** |
| `PHYSICAL-DESIGN-FINALIZATION.md` | **ADDED** (this file) |
| `PHASE-3C-10A-GATE-REPORT.md` | **ADDED** |
| Prior 3C.10 catalogs / gate | **UNCHANGED** (no contradiction rewrite) |
