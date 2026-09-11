# Phase 3C.16 — Revocation

## Implemented

`RevokeAward` → revocation_records + award version lifecycle update (no hard delete).

## Not implemented

| Capability | Reason |
|------------|--------|
| RevokeGraduation (approval-only) | HD-31 / HD-36 OPEN (3C.15A readiness = BLOCKED) |
| Reason code catalog | HD-36-REASONS OPEN |
| Cross-enrollment cascade revoke | Forbidden / not designed |

Revocation never mutates a different enrollment’s Graduation records.
