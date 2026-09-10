# Phase 3C.15 — HD-36 Revocation Policy Decision

## Locked (mechanism)

| Item | Class | Evidence |
|------|-------|----------|
| Revocation only via authorized human action | LOCKED | 3C.8B HD-36 Option A |
| Preserve lineage; never DELETE/OVERWRITE/SILENT auto-revoke | LOCKED | HD-36 + DL-019 |
| `revocation_records` + award version lifecycle | LOCKED (schema) | Phase 3C.12 migrations |
| Reject hard-delete triggers on official history | LOCKED | LIVE triggers |

## Scope clarity (do not conflate)

| Object | Revocation semantics |
|--------|----------------------|
| Graduation Award (issued version) | Primary HD-36 target in model |
| Approval | Distinct; not auto-same as award revoke |
| Completion outcome | Correction via HD-35 supersession path — not silent rewrite |
| Publication | Depends on HD-38 (OPEN) |

## Open

| Item | Classification |
|------|----------------|
| Who may revoke? | **OPEN** → `HD-36-ROLES = POLICY NOT LOCKED` |
| Allowed reason catalog | **OPEN** → `HD-36-REASONS = POLICY NOT LOCKED` |
| Exact legal reason codes | OPEN |

Schema uses opaque reason references — **compatible**; does not invent catalog.

## Implementation impact

```text
Production RevokeGraduationAward with validated reasons + Policy
= IMPLEMENTATION BLOCKED until HD-36-ROLES + HD-36-REASONS
```

Mechanism/lineage design remains aligned with DB.
