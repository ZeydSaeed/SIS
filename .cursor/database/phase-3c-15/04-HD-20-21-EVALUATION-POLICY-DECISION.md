# Phase 3C.15 — HD-20 / HD-21 Evaluation Policy Decision

## Separation

| Layer | Status |
|-------|--------|
| **EVALUATION ENGINE MECHANISM** (versioned policy pin, evidence fail-closed, requirement evaluation rows, immutable official versions) | Architecturally **LOCKED** (HD-20/21 framework, DL-019/020, schema) |
| **EVALUATION BUSINESS CONTENT** (what must be true to complete) | **OPEN** — POLICY NOT LOCKED |

## Locked mechanism facts

| Fact | Class | Evidence |
|------|-------|----------|
| Versioned institution-defined eligibility framework | LOCKED | 3C.8B HD-20 Option A |
| Extensible required-unit model (types not hard-coded) | LOCKED | 3C.8B HD-21 Option A |
| No default GPA gate | LOCKED | 3C.8B HD-22 Option A; DL-021 |
| Missing evidence ≠ satisfied | LOCKED | DL-020 |
| Grades remain Exams SSOT; Graduation not grades SSOT | LOCKED | DL-018 |
| JSONB/policy slots may be empty | LOCKED | D-3C10-007 |

## Content checklist (all OPEN unless cited)

| Topic | Classification |
|-------|----------------|
| Completion requirement definitions (actual) | OPEN |
| Required units / courses lists | OPEN |
| Minimum credits/hours | OPEN |
| Practical training requirements | OPEN |
| Required examinations | OPEN |
| Grade thresholds | OPEN |
| Mandatory vs optional requirements | OPEN |
| Substitutes / equivalents | OPEN |
| Exemptions / waivers | OPEN |
| Transfer credit | OPEN |
| Repeated / failed / incomplete / withdrawn handling | OPEN |
| Concurrent enrollment rules for eval | OPEN |
| Historical curriculum version binding beyond “pin policy version” | OPEN (pin mechanism LOCKED; curriculum catalogs OPEN) |
| Academic-year effective rules (content) | OPEN |
| Evidence requirement catalogs | OPEN |

```text
HD-20/21-CONTENT = POLICY NOT LOCKED
```

**Never invent:** required units, minimum GPA, minimum grade, credit totals, course lists, completion thresholds.

Blueprint `min_gpa` / credits / subjects = **NON-AUTHORITATIVE** (3C.8B).

## Implementation impact

```text
EvaluateCompletion producing official eligibility from real rules
= IMPLEMENTATION BLOCKED
```

Scaffolding that stores empty/versioned policy shells without inventing rules remains conditional on separate implementation authorization — still must not fabricate content.
