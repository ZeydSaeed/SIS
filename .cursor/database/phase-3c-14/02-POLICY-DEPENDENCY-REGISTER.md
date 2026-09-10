# Phase 3C.14 — Policy Dependency Register

**Authority:** Phase 3C.8B Decision Closure Register + DL-017…022 + 3C.10/11/11A/13 docs + LIVE code.  
**Rule:** Cite approved policy only; otherwise `POLICY NOT LOCKED`. No invented values.

| ID | Policy | Current Evidence | Locked? | Affects DB? | Affects App? | Implementation Blocker |
|----|--------|------------------|---------|-------------|--------------|------------------------|
| HD-19 | Completion ≠ Graduation | 3C.8B APPROVED Option A; DL-017 | **LOCKED** | Structure already matches | Command split | No |
| HD-39 | Outcome identity school+enrollment (+ program context) | 3C.8B APPROVED Option A; UNIQUE `(school_id,enrollment_id)` LIVE | **LOCKED** | Identity constraints LIVE | Per-enrollment commands | No (identity) |
| HD-20 | Versioned eligibility **framework** | 3C.8B APPROVED_WITH_CONDITION | **PARTIALLY LOCKED** | Tables/JSONB slots exist; content empty | EvaluateCompletion | **YES** for engine execution |
| HD-20-CONTENT | Actual eligibility rules/thresholds | Explicitly not supplied; blueprint min_gpa NON-AUTHORITATIVE | **NOT LOCKED** | No (nullable JSONB) | Eval engine body | **YES** |
| HD-21 | Extensible required-units **model** | 3C.8B APPROVED_WITH_CONDITION | **PARTIALLY LOCKED** | Extensible requirement tables | Unit evidence eval | Soft until content |
| HD-21-CONTENT | Actual unit lists | Not supplied | **NOT LOCKED** | No | Eval | **YES** if unit-based rules required |
| HD-22 | No default GPA gate | 3C.8B APPROVED Option A; DL-021 | **LOCKED** | No mandatory GPA FK | Must not assume GPA | No |
| HD-31 | Human approval **model** (no silent auto-approve) | 3C.8B APPROVED_WITH_CONDITION | **PARTIALLY LOCKED** | Opaque actor BIGINT | DecideApproval | Model OK; permissions block |
| HD-31-ROLES | Who may request/approve/reject/override | “Roles not invented”; no Graduation permissions in `Permission.php` | **NOT LOCKED** | No | Authz matrix | **YES** for official approval/award commands |
| HD-32 | Award = separate entity after approval | 3C.8B APPROVED_WITH_CONDITION; DL-022 | **PARTIALLY LOCKED** | Award tables LIVE | IssueAward | Structure OK |
| HD-32-ATTRS | Honors/numbering/legal attrs | Open | **NOT LOCKED** | Nullable columns only | Payload/fingerprint fields | Soft (nullable) / **YES** if attrs mandatory |
| HD-35 | Correction via candidate + human supersession | 3C.8B APPROVED Option A; DL-019 | **LOCKED** | Supersession tables/triggers | Supersede commands | No for design |
| HD-36 | Revoke via authorized human + lineage | 3C.8B APPROVED_WITH_CONDITION | **PARTIALLY LOCKED** | revocation_records LIVE | RevokeAward | Mechanism OK |
| HD-36-REASONS/ROLES | Legal reasons + revoke roles | Open | **NOT LOCKED** | Opaque reason_ref | Authz + validation | **YES** for production revoke |
| DL-018 | Graduation not grades/GPA/transcript SSOT | ACCEPTED | **LOCKED** | Evidence refs only | No inventing grades | No |
| DL-020 | Evidence-based; missing ≠ satisfied | ACCEPTED | **LOCKED** | Evidence/eval tables | Eval fail-closed | No |
| DL-022 | StudentStatus::Graduated = projection | ACCEPTED; enum LIVE | **LOCKED** | No graduation SSOT on students | Consumers | No |
| SS-MULTI | Which enrollment flips student-level Graduated | 3C.11A Human Decision Register — HUMAN REQUIRED | **NOT LOCKED** | No schema change needed | Outbox→Students sync | **YES** for auto-sync |
| SS-REVOKE-CLEAR | Clear Graduated when all awards revoked | 3C.11A — HUMAN REQUIRED | **NOT LOCKED** | No | Revoke consumer | **YES** for auto-sync |
| D-3C10-007 | JSONB policy payload nullable; no invented schema | CLOSED | **LOCKED** | Already | Policy upsert | No |
| D-3C10-005 | Outbox event **names** | PROPOSED / deferred | **PARTIALLY LOCKED** (storage FINAL) | Storage OK | Event registration | Soft until governance |
| HD-38 | Publication | Unresolved in 3C.8B | **NOT LOCKED** | N/A | Publication cmds | **YES** for publication |
| SEP-DUTY | Separation of duties (evaluator ≠ approver ≠ issuer) | Not found as approved rule | **NOT LOCKED** | No | Policy checks | Soft / institutional |
| MULTI-STEP-APPR | One-step vs multi-step approval | Schema supports `attempt_no`; workflow depth not locked | **NOT LOCKED** | attempt_no exists | Approval UX | Soft |

## Classification key

| Status | Meaning |
|--------|---------|
| LOCKED | Approved Option/DL with implementable meaning |
| PARTIALLY LOCKED | Structural/model approved; content/roles still open |
| NOT LOCKED | Explicit open / POLICY NOT LOCKED |
| CONFLICTING | None found in this audit |

```text
CONFLICTING ENTRIES: NONE
```
