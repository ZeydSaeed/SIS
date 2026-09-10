# 10 — Database Governance

**Status:** Phase 1 — binding with ADR-020  
**Precedence:** Constitution → Architecture → this governance → convenience

---

## Forbidden without human approval

- Bypass FK integrity  
- Disable RLS or weaken fail-closed policies  
- Delete critical history (grades, enrollments, attendance, finance, audit, medical, discipline)  
- Create uncontrolled duplicate tables/concepts  
- Introduce unindexed high-volume FK filters used in hot paths without analysis  
- Unsafe CASCADE on academic data  
- JSONB replacing relational modeling without justification  
- Modify critical schemas without migration + documentation  
- Rewrite applied migrations  
- UUID internal PKs as a new default (conflicts D1 / ADR-003)  
- Big-bang ERP schema creation (conflicts D3)

---

## Required on every DB change

1. Follow `.cursor/skills/database-change/SKILL.md`  
2. Complete `DATABASE-CHANGE-CHECKLIST.md`  
3. Update `database-blueprint.md`  
4. Cite ADR-020 when touching PK/schema/RLS/admission/assessment  
5. Produce phase gate evidence  

---

## Schema creation rule

New schema only if:

- Responsibility documented in [01-SCHEMA-CATALOG.md](./01-SCHEMA-CATALOG.md)  
- Phase gate approved  
- Not a rename of a valid existing schema for cosmetics  

---

## CHECK constraints

Introduce progressively:

1. Confirm no existing violating rows  
2. Add CHECK in additive migration  
3. Cover with integrity tests  

Phase 1 applies **zero** new CHECKs (design only).

---

## Intelligence safety

Optimization/self-healing must not auto-execute schema destruction or mutate CRITICAL SIS tables. See `DATABASE-INTELLIGENCE-SAFETY.md`.

---

## Blueprint SSOT

| Metric | Value |
|--------|-------|
| Blueprint objects | **87** (tables + MVs in blueprint headings) |
| Schemas in blueprint | **24** (includes `admission`) |
| Intelligence | Separate; not in 87 |

Doc drift that reintroduces 86/89 without recount is a governance defect — fix in the same change.
