# SIS DATABASE — PHASE 3C.7  
# GRADUATION / COMPLETION POLICY DEPENDENCIES

**Document type:** DESIGN ONLY  
**Date:** 2026-09-10  

```text
NO POLICY VALUES · NO THRESHOLDS INVENTED
```

---

## 1. Dependency Graph (conceptual)

```text
HD-04/05/06/07/18 (+ Rounding) ──► Grade/Result eligibility evidence
HD-01/02/15                    ──► GPA evidence (optional)
HD-08/09/10/14                 ──► Ranking evidence (optional, default off)
HD-11/12/16                    ──► Transcript display / packaging after award
DL-001…016                     ──► Results foundation locks
HD-19…HD-42                    ──► Graduation/Completion policy (this phase)
Promotion blueprint            ──► SEPARATE (HD-41) — not graduation policy
```

---

## 2. Upstream Phase Dependencies

| Phase | Dependency |
|-------|------------|
| 3B/3B.1 Grades | Evidence SSOT |
| 3C.2 Term | Optional/required unit evidence |
| 3C.3 Annual | Common completion evidence |
| 3C.4 GPA | Optional if HD-22 requires |
| 3C.5 Ranking | Optional only if future policy |
| 3C.6 Transcript | Consumer of award/completion display |

---

## 3. Policy Version Lifecycle

```text
DRAFT → PUBLISHED → EFFECTIVE → RETIRED
```

Effective dates respected. No in-place rewrite of published policies. Historical outcomes reproduce under pinned policy versions.

---

## 4. Optional vs Hidden Dependencies

| Dependency | Rule |
|------------|------|
| GPA | Include only if completion policy requires; pin version |
| Ranking | Default **not** required |
| Transcript | Never authoritative for graduation |
| Promotion rules | Never reinterpret as graduation |
| Attendance / admin clearance / finance | Extension points until HD says required |
| Blueprint min_gpa / credits | **Not** approved |

---

## 5. Requirement Category Extension Points

Academic achievement · required units · competency · practical · internship · capstone · attendance · institutional · administrative  

None mandatory until HD-20…HD-30 resolve.

---

## 6. Event / Outbox Compatibility

Reuse existing grade/result outbox patterns. Add completion/graduation events only as needed; avoid duplicate taxonomies. Conceptual names:

GRADE_CORRECTED · TERM_RESULT_SUPERSEDED · ANNUAL_RESULT_SUPERSEDED · GPA_SUPERSEDED · COMPLETION_EVALUATED · COMPLETION_STATUS_CHANGED · GRADUATION_APPROVAL_REQUESTED · GRADUATION_APPROVED · GRADUATION_SUPERSEDED · GRADUATION_REVOKED · TRANSCRIPT_IMPACT_DETECTED  

Exact naming aligned at implementation with existing conventions.

---

## 7. 3C / 3D / Lifecycle Packaging

| ID | Topic | Status |
|----|-------|--------|
| HD-16 | Transcript packaging 3C vs 3D | Deferred (prior) |
| HD-42 | Graduation module delivery vs Results phase naming | **Unresolved** |

Architectural recommendation: same monolith; lifecycle module consumes Results; no microservice split (DL-012).
