# SIS DATABASE — PHASE 3C.8B  
# DECISION CLOSURE REGISTER

**Document type:** HUMAN DECISION CLOSURE RECORD  
**Date:** 2026-09-10  
**Authoritative for:** HD-19, HD-20, HD-21, HD-22, HD-31, HD-32, HD-35, HD-36, HD-39 + related lock effects  

```text
MODE = HUMAN DECISION CLOSURE + DECISION CONSOLIDATION ONLY
IMPLEMENTATION AUTHORIZATION = NOT GRANTED
POLICY CONTENT INVENTION = FORBIDDEN
Phase 3C.7 / 3C.8 / 3C.8A remain historical records — not rewritten
```

---

## Validation Summary (V1–V8)

| Check | Result |
|-------|--------|
| V1 HD consistency vs 3C.7/3C.8/3C.8A Option A packs | **PASS** — all match workshop Option A |
| V2 DL-017…022 vs approved HDs | **PASS** — coherent (see Decision Locks) |
| V3 HD-35 + HD-36 + DL-019 historical model | **PASS** |
| V4 HD-19 + HD-39 identity (not student_id-only) | **PASS** |
| V5 HD-22 + DL-021 no hidden GPA | **PASS** |
| V6 HD-31 + HD-32 no machine-only graduation | **PASS** |
| V7 HD-19 + HD-32 + DL-022 StudentStatus projection | **PASS** |
| V8 Policy neutrality (no invented thresholds) | **PASS** |

**Conflicts found:** None between approved Option A decisions and predecessor architecture.  
**Stale docs:** Blueprint `min_gpa` / credits / subjects remain **NON-AUTHORITATIVE** (unchanged files).

---

## HD-19 — Completion vs Graduation

| Field | Content |
|-------|---------|
| **HD-ID** | HD-19 |
| **Priority** | P0 |
| **Impact Surface** | DOMAIN, IDENTITY, LOGICAL_SCHEMA, PHYSICAL_SCHEMA, AWARD, TRANSCRIPT, STUDENT_STATUS, AUDIT, PROVENANCE |
| **Exact Question** | Maintain Completion and Graduation as distinct official concepts? |
| **Human Decision** | **APPROVED — OPTION A** |
| **Selected Option** | A |
| **Architectural Meaning** | `Completion ≠ Graduation`. Completion = satisfaction of completion/eligibility requirements. Graduation = official graduation/award after required approval. Not `is_graduated` / not StudentStatus SSOT. |
| **Policy Content Still Required** | Institutional legal/operational naming/labels only (optional); no thresholds |
| **Dependencies** | Enables DL-017; informs HD-32–34 |
| **Blocks** | Unblocks dual-outcome identity design (schema still not authorized) |
| **Historical Consequences** | Separate lineages possible for completion vs award |
| **Security Consequences** | Separate approve/award authz possible later |
| **Schema Consequences** | Distinct Completion vs Award entities expected in future design |
| **Engine Consequences** | Eval may produce completion; graduation requires approval path |
| **Status** | **APPROVED** |

---

## HD-20 — Graduation Eligibility Policy Framework

| Field | Content |
|-------|---------|
| **HD-ID** | HD-20 |
| **Priority** | P0 |
| **Impact Surface** | DOMAIN, POLICY, EVALUATION_ENGINE, LOGICAL_SCHEMA, PROVENANCE, HISTORICAL_INTEGRITY |
| **Exact Question** | Adopt versioned institution-defined eligibility policy framework? |
| **Human Decision** | **APPROVED — OPTION A** |
| **Selected Option** | A |
| **Architectural Meaning** | Architectural framework for versioned policies + reproducible evaluation is approved. **Policy content values = NOT INVENTED / NOT SUPPLIED.** Blueprint min_gpa/credits/subjects/attendance are not authoritative. |
| **Policy Content Still Required** | **YES** — actual eligibility rules, categories, thresholds (institution input) |
| **Dependencies** | Unlocks structure for HD-21…30; HD-22 remains no-default-GPA |
| **Blocks** | Engine **execution against real rules** still **POLICY INPUT REQUIRED**; framework design unblocked |
| **Historical Consequences** | Evaluations must pin policy version |
| **Security Consequences** | Policy publish/effective authority TBD by institution |
| **Schema Consequences** | Requirement/policy version tables expected later |
| **Engine Consequences** | Engine must refuse inventing defaults; load published policy versions only |
| **Status** | **APPROVED_WITH_CONDITION** (framework yes; content open) |

---

## HD-21 — Required Academic Units

| Field | Content |
|-------|---------|
| **HD-ID** | HD-21 |
| **Priority** | P0 |
| **Impact Surface** | DOMAIN, POLICY, EVALUATION_ENGINE, IDENTITY, LOGICAL_SCHEMA |
| **Exact Question** | Are required academic units institution-defined and versioned with extensible types? |
| **Human Decision** | **APPROVED — OPTION A** |
| **Selected Option** | A |
| **Architectural Meaning** | Required units are institution-defined + versioned; architecture extensible (subject/course/module/competency/credits/… as **examples only**, not approved requirement lists). |
| **Policy Content Still Required** | **YES** — actual required unit sets |
| **Dependencies** | HD-20 framework |
| **Blocks** | Engine unit evaluation until content supplied |
| **Historical Consequences** | Pin unit-policy version on evaluations |
| **Security Consequences** | None beyond school-scoped policy |
| **Schema Consequences** | Extensible requirement/unit typing — not hard-coded single type |
| **Engine Consequences** | No hard-coded permanent single unit type |
| **Status** | **APPROVED_WITH_CONDITION** (model yes; unit lists open) |

---

## HD-22 — GPA / Minimum Achievement Gate

| Field | Content |
|-------|---------|
| **HD-ID** | HD-22 |
| **Priority** | Was conditional P0; now closed as no-default |
| **Impact Surface** | POLICY, EVALUATION_ENGINE, PROVENANCE |
| **Exact Question** | Is there a default GPA/minimum-achievement graduation gate? |
| **Human Decision** | **APPROVED — OPTION A** |
| **Selected Option** | A |
| **Architectural Meaning** | **NO default GPA/min-achievement gate.** Future GPA requirement allowed only via explicit versioned policy linked to authoritative GPA/calculation policy. Never blueprint `min_gpa`. No threshold created. |
| **Policy Content Still Required** | Only if institution later opts into GPA gate (then HD-01/15/Rounding also) |
| **Dependencies** | Aligns DL-021; does not require HD-01 for graduation v1 |
| **Blocks** | Nothing for no-GPA path; GPA-gated path remains future explicit |
| **Historical Consequences** | Official evals must not imply hidden GPA rule |
| **Security Consequences** | N/A |
| **Schema Consequences** | Optional GPA evidence link — not mandatory FK |
| **Engine Consequences** | Must not assume GPA check |
| **Status** | **APPROVED** |

---

## HD-31 — Graduation Approval Authority

| Field | Content |
|-------|---------|
| **HD-ID** | HD-31 |
| **Priority** | P0 |
| **Impact Surface** | APPROVAL, CQRS, SECURITY, AUDIT, DOMAIN |
| **Exact Question** | Is graduation approval human-controlled (no silent ELIGIBLE→APPROVED/GRADUATED)? |
| **Human Decision** | **APPROVED — OPTION A** |
| **Selected Option** | A |
| **Architectural Meaning** | System may evaluate/calculate/detect eligibility/prepare candidate/request approval/record decision. Must **not** silently convert ELIGIBLE→APPROVED or ELIGIBLE→GRADUATED. Authority model = **HUMAN APPROVAL REQUIRED**. Roles/committees/permissions/org hierarchy **not invented**. |
| **Policy Content Still Required** | **YES** — real authorization model / role mapping (institution) |
| **Dependencies** | Precedes HD-32 award in lifecycle |
| **Blocks** | Binding permissions until roles supplied; approval *model* closed |
| **Historical Consequences** | Approver actor must be recorded when roles exist |
| **Security Consequences** | Fail closed without authorized human approver at impl |
| **Schema Consequences** | Approval actor slots; no fabricated role enum as policy |
| **Engine Consequences** | No auto-approve transition |
| **Status** | **APPROVED_WITH_CONDITION** (model yes; role matrix open) |

---

## HD-32 — Graduation Award Semantics

| Field | Content |
|-------|---------|
| **HD-ID** | HD-32 |
| **Priority** | P0 |
| **Impact Surface** | AWARD, DOMAIN, LOGICAL_SCHEMA, TRANSCRIPT, STUDENT_STATUS, AUDIT |
| **Exact Question** | Is Graduation Award a separate official record after approval? |
| **Human Decision** | **APPROVED — OPTION A** |
| **Selected Option** | A |
| **Architectural Meaning** | Lifecycle: Completion → Eligibility → Human Approval → Graduation Award. Award ≠ StudentStatus::Graduated; ≠ mere approval flag. Honors/classification/certificate numbers/legal attrs = future policy. |
| **Policy Content Still Required** | **YES** — honors, classification, numbering, legal attributes |
| **Dependencies** | HD-19, HD-31 |
| **Blocks** | Award content fields until policy; Award *entity concept* closed |
| **Historical Consequences** | Award versions/lineage via HD-35/36 |
| **Security Consequences** | Award issuance authz TBD with roles |
| **Schema Consequences** | Separate Award record in future design |
| **Engine Consequences** | Award issuance after approval only |
| **Status** | **APPROVED_WITH_CONDITION** (structure yes; award attributes open) |

---

## HD-35 — Correction After Official Graduation

| Field | Content |
|-------|---------|
| **HD-ID** | HD-35 |
| **Priority** | P0 |
| **Impact Surface** | CORRECTION, HISTORICAL_INTEGRITY, LOGICAL_SCHEMA, EVALUATION_ENGINE, CQRS, AUDIT, PROVENANCE, TRANSCRIPT, STUDENT_STATUS |
| **Exact Question** | How do upstream evidence changes affect official outcomes? |
| **Human Decision** | **APPROVED — OPTION A** |
| **Selected Option** | A |
| **Architectural Meaning** | Keep original official outcome → detect impact → new evaluation candidate → human review → explicit supersession if required → new official outcome. Never silent mutate/rewrite. Never auto-revoke solely because a grade changed. |
| **Policy Content Still Required** | Operational details of “material impact” / review SLAs (optional refinements) |
| **Dependencies** | DL-019; pairs with HD-36 |
| **Blocks** | Unblocks correction design; impl still not authorized |
| **Historical Consequences** | Immutable originals + lineage |
| **Security Consequences** | Human gate on official transition |
| **Schema Consequences** | Supersession/candidate version model required |
| **Engine Consequences** | Impact detection + candidate prep; no auto official flip |
| **Status** | **APPROVED** |

---

## HD-36 — Revocation Policy

| Field | Content |
|-------|---------|
| **HD-ID** | HD-36 |
| **Priority** | P0 |
| **Impact Surface** | CORRECTION, HISTORICAL_INTEGRITY, APPROVAL, AWARD, AUDIT, SECURITY, STUDENT_STATUS |
| **Exact Question** | How may official outcomes be revoked? |
| **Human Decision** | **APPROVED — OPTION A** |
| **Selected Option** | A |
| **Architectural Meaning** | Revocation only via authorized human action; preserve lineage. Never DELETE/OVERWRITE/SILENT/AUTO-revoke without approved authority. Preserve original, event, authority, reason, timestamp, lineage, supersession. Exact legal reasons/roles later. |
| **Policy Content Still Required** | **YES** — legal/organizational reasons; authorized roles |
| **Dependencies** | HD-35; DL-019 |
| **Blocks** | Role/reason catalogs; revoke *mechanism* closed |
| **Historical Consequences** | Full audit lineage mandatory |
| **Security Consequences** | Fail closed without authority |
| **Schema Consequences** | Revocation/supersession events; no hard delete |
| **Engine Consequences** | No auto-revoke |
| **Status** | **APPROVED_WITH_CONDITION** (mechanism yes; reasons/roles open) |

---

## HD-39 — Multi-Program Graduation

| Field | Content |
|-------|---------|
| **HD-ID** | HD-39 |
| **Priority** | P0 |
| **Impact Surface** | IDENTITY, DOMAIN, LOGICAL_SCHEMA, PHYSICAL_SCHEMA, EVALUATION_ENGINE, SECURITY, RLS |
| **Exact Question** | Scope outcomes by school + enrollment/program context (multiple independent outcomes allowed)? |
| **Human Decision** | **APPROVED — OPTION A** |
| **Selected Option** | A |
| **Architectural Meaning** | Outcomes scoped via school + enrollment + program/context per live enrollment model. **Not student_id only.** Multiple independent outcomes allowed. School isolation retained. No one-lifetime-global graduation record forced. |
| **Policy Content Still Required** | Concurrent-program edge cases may need later refinement |
| **Dependencies** | HD-19 |
| **Blocks** | Unblocks identity for logical schema design request |
| **Historical Consequences** | Per-context lineages |
| **Security Consequences** | School fail-closed preserved |
| **Schema Consequences** | Business identity includes school + enrollment/program context |
| **Engine Consequences** | Evaluate per scoped identity |
| **Status** | **APPROVED** |

---

## Still Open (explicit non-decisions)

```text
UNRESOLVED / POLICY INPUT REQUIRED (examples — not exhaustive):
- actual GPA threshold (none by default per HD-22)
- actual required subject/module/course lists
- actual credit / attendance thresholds
- honors / classification / certificate numbering
- revocation legal reasons; approval role names; committees; permissions; org hierarchy
- publication authority; transcript publication rules
- HD-23…30, HD-33, HD-34, HD-37, HD-38, HD-40, HD-41, HD-42 (not closed in this phase unless already deferred elsewhere)
```

HD-16 remains **DEFERRED** (prior). HD-01…18 unresolved as prior where not superseded by these closures.
