# SIS DATABASE — PHASE 3C.9  
# POLICY INVENTION DEFENSE & INVENTORY

**Document type:** AUDIT + LOGICAL DESIGN BOUNDARY  
**Date:** 2026-09-10  

```text
No academic thresholds invented in Phase 3C.9.
```

---

## 1. Repository Scan Classification

| Pattern / Location | Classification |
|--------------------|----------------|
| Blueprint `graduation.eligibility_rules.min_gpa` | **STALE** / NON-AUTHORITATIVE |
| Blueprint `min_credit_hours` / `required_subjects` | **STALE** |
| Blueprint `graduation.records.final_gpa` / `honors` | **STALE** sketch attributes — honors = **UNRESOLVED** policy |
| Blueprint `promotion.rules.min_gpa` | **STALE** as graduation; promotion-only sketch |
| PHASE-D graduation table list | **EXAMPLE** / planning NON-AUTHORITATIVE |
| HD-22 APPROVED no default GPA gate | **AUTHORITATIVE** (3C.8B) |
| DL-021 ACCEPTED | **AUTHORITATIVE** |
| 3C.7/3C.8 “must not invent thresholds” | **AUTHORITATIVE** invariant language |
| Workshop Option texts | **PROPOSED** options (historical) — closed where 3C.8B approved |
| GC-INV missing ≠ satisfied | **AUTHORITATIVE** architectural invariant |
| `attendance_threshold` in graduation context | **Not found as approved** — **UNRESOLVED** if ever proposed |
| Certificate numbering | **UNRESOLVED** |

---

## 2. Logical Schema Guardrails

| Guard | Enforcement in logical model |
|-------|------------------------------|
| No required GPA attribute on every evaluation | Optional EvidenceItem only |
| No seeded RequirementDefinitionVersion values | Empty catalog until policy input |
| No role/committee enums as policy | authority_ref opaque |
| No honors codes as required columns | Optional extension attrs marked POLICY INPUT |
| Blueprint not used as default | Explicit non-reuse of graduation.* sketch |

---

## 3. Policy Inputs Still Required (not invented)

```text
Eligibility rule content
Required unit lists
Category rules HD-23…30
Approval role mapping HD-31
Award attributes (honors, numbers) HD-32
Revocation reasons HD-36
Publication HD-38
Date semantics HD-33/34
Retention years HD-37
```

---

## 4. Recommended Doc Markers (not applied this phase)

```text
database-blueprint.md graduation.* / promotion.min_gpa as graduation
→ STALE / NON-AUTHORITATIVE
```
