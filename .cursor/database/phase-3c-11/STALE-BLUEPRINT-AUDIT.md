# PHASE 3C.11 — STALE BLUEPRINT AUDIT

**Do not delete or modify these files in this phase.**

| Location | Classification | Notes |
|----------|----------------|-------|
| `database-blueprint.md` → `graduation.eligibility_rules` | **STALE / CONFLICTING** | min_gpa, credits — NON-AUTHORITATIVE vs HD-22 / 3C.10 |
| `database-blueprint.md` → `graduation.records` | **STALE / CONFLICTING** | Do not implement |
| `database-blueprint.md` → certificates.graduation_id → records | **STALE** | Re-bind later to award version — not now |
| `database-blueprint.md` → `reports.mv_graduation_statistics` | **STALE / REFERENCE ONLY** | Future reporting |
| `scalability-and-async.md` graduation.records refs | **SUPERSEDED** | Points at stale tables |
| Phase 3C.7–3C.8 architecture docs | **AUTHORITATIVE** for decisions; historical for process | Locks in 3C.8B |
| Phase 3C.9 logical | **AUTHORITATIVE** logical | |
| Phase 3C.10 / 3C.10A | **AUTHORITATIVE** physical | |
| Phase 0 reserved schema list including graduation | **AUTHORITATIVE** namespace | Empty schema OK |

### Implementation plan rule

```text
Do NOT use stale names: eligibility_rules, graduation.records, min_gpa columns
Use 3C.10 names only
```
