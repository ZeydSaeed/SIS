# Phase 3C.15 — HD-38 Publication Decision

## Finding

HD-38 was **not closed** in Phase 3C.8B (listed under still-open / publication authority unresolved).

| Question | Classification |
|----------|----------------|
| What is published? | OPEN |
| Who may publish? | OPEN |
| Is publication mandatory for official Graduation? | OPEN |
| Separate from approval? | OPEN |
| Reversible? | OPEN |
| Creates immutable official version? | OPEN (award/completion already have version immutability LOCKED separately) |
| School-scoped? | DERIVED likely yes if exists (HD-39) — feature itself OPEN |
| Externally official? | OPEN |
| Sync vs async? | OPEN |
| Required before Graduation official? | OPEN |

```text
HD-38 = POLICY NOT LOCKED
```

## Classification for implementation gating

Until humans either:

1. Approve a publication policy, or  
2. Explicitly mark publication **out of critical path**,

treat publication as:

```text
DEFERRED FEATURE (recommended interim stance for readiness matrix)
```

**Important:** Absence of HD-38 must **not** be used to invent a publisher role.  
Core Completion→Approval→Award path can remain conceptually separate; publication must not silently become mandatory in code.

## Implementation impact

```text
PublishAward / publication_* commands = IMPLEMENTATION BLOCKED / DEFERRED
Does not by itself unlock HD-31 or eval content
```
