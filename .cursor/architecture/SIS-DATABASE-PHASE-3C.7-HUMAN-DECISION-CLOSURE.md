# SIS DATABASE — PHASE 3C.7  
# HUMAN DECISION CLOSURE — GRADUATION / COMPLETION

**Document type:** GOVERNANCE REGISTER  
**Date:** 2026-09-10  

```text
NO DECISIONS SILENTLY RESOLVED
Preserve HD-01…HD-18 identities — do not renumber
```

---

## 1. Prior Decisions (carry forward)

| ID | Topic | Status |
|----|-------|--------|
| HD-01…HD-15, HD-17, HD-18 | Results/GPA/ranking/eligibility | **UNRESOLVED** (prior) |
| HD-16 | 3C/3D transcript boundary | **DEFERRED** (prior) |
| Rounding | Rounding policy | **UNRESOLVED** (prior) |
| DL-001…DL-016 | Results locks | **Preserved** |

---

## 2. New Graduation / Completion Decisions

| ID | Topic | Status | Notes |
|----|-------|--------|-------|
| **HD-19** | Graduation vs Completion semantics | **UNRESOLVED** | Architecture distinguishes; human confirms naming/legal use |
| **HD-20** | Graduation eligibility policy | **UNRESOLVED** | No invented rules |
| **HD-21** | Required academic units | **UNRESOLVED** | |
| **HD-22** | Minimum achievement/GPA if any | **UNRESOLVED** | Blueprint min_gpa **not** approved |
| **HD-23** | Failed-unit policy | **UNRESOLVED** | |
| **HD-24** | Incomplete/withdrawn/exempt treatment | **UNRESOLVED** | Links HD-05 |
| **HD-25** | Repeat/retake treatment | **UNRESOLVED** | Links HD-06 |
| **HD-26** | Transfer-credit treatment | **UNRESOLVED** | Links HD-07 |
| **HD-27** | Practical/internship requirements | **UNRESOLVED** | |
| **HD-28** | Capstone/project requirements | **UNRESOLVED** | |
| **HD-29** | Attendance requirement if any | **UNRESOLVED** | |
| **HD-30** | Administrative clearance if any | **UNRESOLVED** | Finance/conduct/etc. |
| **HD-31** | Graduation approval authority | **UNRESOLVED** | Roles/workflow |
| **HD-32** | Graduation award semantics | **UNRESOLVED** | Honors/classifications |
| **HD-33** | Graduation date semantics | **UNRESOLVED** | |
| **HD-34** | Completion date semantics | **UNRESOLVED** | Distinct from HD-33 |
| **HD-35** | Correction after graduation | **UNRESOLVED** | Not auto-revoke |
| **HD-36** | Revocation policy | **UNRESOLVED** | |
| **HD-37** | Historical retention | **UNRESOLVED** | No invented years |
| **HD-38** | Publication policy | **UNRESOLVED** | |
| **HD-39** | Multi-program graduation | **UNRESOLVED** | |
| **HD-40** | Graduation vs academic-year closure | **UNRESOLVED** | Default: independent |
| **HD-41** | Graduation vs promotion | **UNRESOLVED** | Default: separate |
| **HD-42** | 3C/Results vs lifecycle delivery boundary | **UNRESOLVED** | No microservice; packaging TBD |

**RESOLVED in this phase:** none.

---

## 3. P0 for Official Engine Implementation

```text
HD-19, HD-20, HD-21, HD-31, HD-32, HD-35
(+ HD-22 if GPA-gated)
(+ HD-23…30 as required by chosen policy model)
```

---

## 4. Explicit Non-Approvals

The following are **NOT** human-approved by virtue of appearing in blueprints:

- `graduation.eligibility_rules.min_gpa`
- `graduation.eligibility_rules.min_credit_hours`
- `graduation.eligibility_rules.required_subjects`
- `promotion.rules.min_gpa` as graduation rule
- Any hard-coded pass counts / credits / percentages in sketches

---

## 5. Closure Rule

```text
HUMAN DECISION REQUIRED
```

until an authoritative signed acceptance updates this register.
