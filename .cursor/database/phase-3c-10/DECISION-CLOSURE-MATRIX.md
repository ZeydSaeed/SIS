# PHASE 3C.10A — DECISION CLOSURE MATRIX

**Authority:** Phase 3C.10 physical design + 3C.8B locks + 3C.9 logical model  
**Mode:** Decision closure only — no DDL  

Statuses used: `CLOSED` | `DEFERRED WITH SAFE DEFAULT` | `BLOCKED — HUMAN DECISION REQUIRED` | `SUPERSEDED` | `NOT APPLICABLE`

| ID | Question | Recommendation | Status | Blocks 3C.11? |
| -- | -------- | -------------- | ------ | ------------- |
| D-3C10-001 | PostgreSQL schema name | Use reserved schema `graduation` | CLOSED | NO |
| D-3C10-002 | Exclusion on policy effective ranges | No EXCLUSION at launch; overlap FORBIDDEN via partial UNIQUE / app publish path | DEFERRED WITH SAFE DEFAULT | NO |
| D-3C10-003 | evidence_sets PK style | Separate BIGINT IDENTITY + UNIQUE(`completion_outcome_version_id`) | CLOSED | NO |
| D-3C10-004 | Partition evidence_items | No partition at launch; WATCHLIST | DEFERRED WITH SAFE DEFAULT | NO |
| D-3C10-005 | Outbox event names | Storage = `audit.outbox_messages`; names = 3C.9 dotted PROPOSED set | DEFERRED WITH SAFE DEFAULT | NO |
| D-3C10-006 | Actor column FK vs opaque | Opaque nullable BIGINT; no role/user FK until identity locked | DEFERRED WITH SAFE DEFAULT | NO |
| D-3C10-007 | JSONB policy/requirement payload | Nullable JSONB columns; no invented content schema | CLOSED | NO |
| D-3C10-008 | StudentStatus projection table | No new projection table; sync existing `students` status via outbox | CLOSED | NO |
| EVT-STORAGE | Second outbox/event store? | Forbidden — reuse `audit.outbox_messages` only | CLOSED | NO |
| IDEM-STORAGE | Competing idempotency store? | Forbidden — reuse `audit.idempotency_keys` only | CLOSED | NO |
| PART-LAUNCH | Partition any graduation table at launch? | NO PARTITION AT LAUNCH | CLOSED | NO |
| HD-20 content | Eligibility rule values | Institution input — not invented | DEFERRED WITH SAFE DEFAULT | NO |
| HD-21 content | Required unit lists | Institution input — not invented | DEFERRED WITH SAFE DEFAULT | NO |
| HD-31 roles | Approver role catalog | Opaque actors — roles later | DEFERRED WITH SAFE DEFAULT | NO |
| HD-32 attrs | Award attribute catalog | Nullable optional attrs only | DEFERRED WITH SAFE DEFAULT | NO |
| HD-33/34 | graduation_date semantics | Column may exist nullable; meaning open | DEFERRED WITH SAFE DEFAULT | NO |
| HD-23…30,37,38,40–42 | Other open HDs | Outside physical table boundaries | DEFERRED WITH SAFE DEFAULT | NO |

**Blocks 3C.11?** = blocks *implementation planning* (not production engine go-live).

```text
Implementation-critical physical decisions with Blocks 3C.11 = YES: NONE
```

---

## Detailed closure records

### D-3C10-001 — Schema name

| Field | Value |
|-------|-------|
| QUESTION | `graduation` vs `lifecycle` vs other? |
| CURRENT DESIGN | Schema `graduation` for all Completion/Graduation tables |
| OPTIONS | A `graduation` · B `lifecycle` · C `academic_completion` |
| RECOMMENDATION | **A — `graduation`** |
| IMPACT | Matches `SchemaHelper::schemas()`, Phase 0 reserved empty schema, blueprint namespace (tables still STALE) |
| RISK | LOW — naming only; stale blueprint table names still must not be implemented |
| STATUS | **CLOSED** |

```text
FINAL RECOMMENDATION: graduation
RATIONALE: Already reserved in LIVE schema governance (SchemaHelper); 3C.9 ownership; empty today; renaming would create migration churn with no integrity benefit.
STATUS: CLOSED
```

---

### D-3C10-002 — Temporal exclusion

| Field | Value |
|-------|-------|
| QUESTION | Require PostgreSQL EXCLUSION on overlapping policy effective ranges? |
| CURRENT DESIGN | effective_from / effective_to on policy versions |
| OPTIONS | A EXCLUSION now · B No exclusion; partial UNIQUE current-effective · C App-only |
| RECOMMENDATION | **B** — NO EXCLUSION REQUIRED at launch |
| IMPACT | Domain overlap remains FORBIDDEN; enforcement via publish workflow + partial UNIQUE `(policy_id) WHERE is_current_effective` (or equivalent lifecycle flag) |
| RISK | MEDIUM concurrent publish without txn discipline — mitigated by UNIQUE + idempotency |
| STATUS | **DEFERRED WITH SAFE DEFAULT** |

| Table | Temporal columns | Overlap semantics | Recommended enforcement | Reason |
|-------|------------------|-------------------|-------------------------|--------|
| eligibility_policy_versions | effective_from, effective_to | FORBIDDEN for two concurrent “effective” rows of same policy | Partial UNIQUE current-effective + CHECK from≤to; optional EXCLUSION later | EXCLUSION adds GiST/migration cost without launch need |
| requirement_definition_versions | version lifecycle, not calendar overlap primary | ALLOWED multiple published historical versions; one active pin via policy binding | version_no UNIQUE; no EXCLUSION | Versions are sequenced, not range-exclusive |
| completion_outcome_versions | evaluated_at / created_at | Multiple versions ALLOWED; one current official | Partial UNIQUE is_current_official | Not validity-range overlap |
| graduation_award_versions | awarded_at | Multiple versions ALLOWED; one current issued | Partial UNIQUE is_current_issued | Revocation clears current |

```text
NO EXCLUSION REQUIRED
```

---

### D-3C10-003 — evidence_sets identity

| Field | Value |
|-------|-------|
| QUESTION | Shared PK with completion_outcome_version vs separate identity? |
| CURRENT DESIGN | Separate table 1:1 with version |
| OPTIONS | A Shared PK · B Separate BIGINT + UNIQUE(version_id) |
| RECOMMENDATION | **B** |
| IMPACT | Consistent PK style; UNIQUE enforces 1:1 |
| RISK | LOW |
| STATUS | **CLOSED** |

---

### D-3C10-004 — Partitioning

| Field | Value |
|-------|-------|
| QUESTION | When to partition evidence_items? |
| CURRENT DESIGN | No partition |
| OPTIONS | A Early RANGE · B No launch partition + watchlist |
| RECOMMENDATION | **B** |
| IMPACT | Avoids FK/UNIQUE/RLS partition complexity |
| RISK | LOW at baseline scale |
| STATUS | **DEFERRED WITH SAFE DEFAULT** (WATCHLIST — not forbidden forever) |

---

### D-3C10-005 — Event governance

| Field | Value |
|-------|-------|
| QUESTION | Approve outbox event names? |
| CURRENT DESIGN | Events staged to `audit.outbox_messages.event_type` |
| OPTIONS | A Approve SCREAMING names from 3C.10 · B Align to 3C.9 dotted PROPOSED · C Invent new |
| RECOMMENDATION | **B** — planning placeholders from 3C.9; governance approval before production consumers |
| IMPACT | Physical storage unchanged; consumer contracts deferred |
| RISK | LOW for schema; MEDIUM for premature consumer coupling |
| STATUS | **DEFERRED WITH SAFE DEFAULT** |

| Event name (planning placeholder) | Aggregate | Triggering transition | Classification |
|-----------------------------------|-----------|----------------------|----------------|
| completion.evaluated | CompletionOutcomeVersion | Evaluation recorded | PROPOSED |
| completion.eligibility_determined | CompletionOutcomeVersion | Eligibility status set | PROPOSED |
| graduation.approval_requested | GraduationApproval | Request created | PROPOSED |
| graduation.approval_decided | GraduationApproval | Approved/rejected | PROPOSED |
| graduation.award_issued | GraduationAwardVersion | Issued | PROPOSED |
| graduation.outcome_superseded | Completion/Award version | Supersession edge | PROPOSED |
| graduation.award_revoked | RevocationRecord | Revoke append | PROPOSED |
| graduation.publication_requested / published | Optional later | Publication | DEFERRED |

3C.10 SCREAMING names (`COMPLETION_EVALUATED`, …) → **SUPERSEDED** as naming style by 3C.9 dotted placeholders (same semantic transitions).

Payload identity: ids + school_id + correlation_id + version ids only — **no invented business thresholds in payload**. Schema/version of payload: event governance later.

---

### D-3C10-006 — Actor columns

| Field | Value |
|-------|-------|
| QUESTION | FK to users vs opaque BIGINT? |
| CURRENT DESIGN | created_by / decided_by / revoked_by BIGINT nullable |
| OPTIONS | A FK users · B Opaque BIGINT |
| RECOMMENDATION | **B** until user/role model locked (HD-31) |
| IMPACT | No invented roles; additive FK later possible |
| RISK | LOW orphan actor ids — acceptable vs premature coupling |
| STATUS | **DEFERRED WITH SAFE DEFAULT** |

---

### D-3C10-007 — JSONB columns

| Field | Value |
|-------|-------|
| QUESTION | Require JSONB at create vs defer columns? |
| CURRENT DESIGN | Optional payloads on policy/requirement versions |
| OPTIONS | A Omit columns · B Nullable JSONB · C NOT NULL '{}' with schema |
| RECOMMENDATION | **B** — nullable; empty until HD-20/21 content authored |
| IMPACT | Physical shape stable; no invented JSON schema |
| RISK | LOW |
| STATUS | **CLOSED** |

---

### D-3C10-008 — Projection

| Field | Value |
|-------|-------|
| QUESTION | New StudentStatus projection table? |
| CURRENT DESIGN | No graduation projection table |
| OPTIONS | A New table · B Computed only · C Existing students.status via outbox |
| RECOMMENDATION | **C** — existing `StudentStatus::Graduated` on students module; **no new SSOT/projection table** |
| IMPACT | Preserves DL-022; rebuild from current non-revoked award |
| RISK | LOW if consumers treat status as projection |
| STATUS | **CLOSED** |

```text
source: graduation_award_versions (current issued, non-revoked) [+ completion official as needed for completed≠graduated UI]
rebuild_strategy: outbox consumer updates students.status; rebuild job from awards SSOT
consistency_model: eventual (outbox)
failure_recovery: replay/rebuild from SSOT — never trust status alone
outbox_dependency: YES (audit.outbox_messages)
lag_policy: NOT INVENTED — ops/governance later
```

```text
SSOT ≠ projection — CONFIRMED
```
