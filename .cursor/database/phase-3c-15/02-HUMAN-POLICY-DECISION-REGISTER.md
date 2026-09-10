# Phase 3C.15 — Human Policy Decision Register

**Mode:** Formal register only — no invented values.  
**Date:** 2026-09-11  

## Predecessor path audit

| Path requested | Status |
|----------------|--------|
| `.cursor/database/phase-3c-7/**` | **MISSING** (content lives under `.cursor/architecture/SIS-DATABASE-PHASE-3C.7*` historically) |
| `.cursor/database/phase-3c-8/**` | **MISSING** |
| `.cursor/database/phase-3c-8B/**` | **MISSING** as folder; **EXISTS** as `.cursor/architecture/SIS-DATABASE-PHASE-3C.8B-*` |
| `.cursor/database/phase-3c-9/**` | **MISSING** (architecture docs) |
| `.cursor/database/phase-3c-10` … `phase-3c-14` | **EXISTS** |
| `app/`, `routes/`, `database/`, `tests/`, `config/` | Inspected |

## Evidence classification legend

| Tag | Meaning |
|-----|---------|
| LOCKED | Explicit authoritative evidence |
| DERIVED | Strictly implied by a LOCKED decision (no institutional guess) |
| OPEN | Institutional/business judgment still required |
| CONFLICT | Authoritative sources disagree — **none found** |

---

## Master decision matrix

| Decision | Current state | Evidence | Required human decision | Implementation impact |
|----------|---------------|----------|-------------------------|------------------------|
| HD-31 approval **model** (human, no silent auto) | LOCKED | 3C.8B Closure Register HD-31 Option A | — | Must not auto-approve |
| HD-31-ROLES / permission catalog | OPEN | No `graduation.*` in `Permission.php`; 3C.8B “roles not invented”; 3C.14 | Supply institution roles + permission strings + SoD if any | Blocks Approve/Issue/Revoke Policy binding |
| Graduation approval permission names | OPEN | Not in repo | Approve exact permission identifiers | Blocks authz code |
| HD-20 framework | LOCKED | 3C.8B APPROVED_WITH_CONDITION | — | Versioned policy tables OK |
| HD-20/21 evaluation **content** | OPEN | 3C.8B “content = POLICY INPUT”; D-3C10-007 empty JSONB; blueprint NON-AUTHORITATIVE | Publish actual rules/unit lists/thresholds/exemptions | Blocks EvaluateCompletion engine body |
| Evaluation units lists | OPEN | HD-21 content open | Institution unit sets | Blocks unit-based eval |
| Evaluation thresholds (GPA/credits/etc.) | OPEN | HD-22 = no default GPA; no other thresholds locked | Explicit gates if any | Must not invent GPA |
| Exceptions / waivers / transfer | OPEN | Not found as Graduation policy | Define if in-scope | Blocks exception paths |
| Multi-enrollment **SSOT identity** | LOCKED | HD-39; UNIQUE `(school_id,enrollment_id)` | — | Do not change grain |
| SS-MULTI (student-level Graduated flip) | OPEN | 3C.11A Human Decision Register | Which enrollment(s) drive `students.status` | Blocks StudentStatus auto-sync |
| SS-REVOKE-CLEAR | OPEN | 3C.11A | When revoke clears Graduated | Blocks revoke consumer |
| HD-36 revocation **mechanism** | LOCKED | 3C.8B Option A + DL-019 | — | Lineage/no hard delete |
| HD-36-ROLES | OPEN | 3C.8B content still required | Who may revoke | Blocks Revoke authz |
| HD-36-REASONS | OPEN | 3C.8B; schema uses opaque `reason` refs | Legal/org reason catalog | Blocks reason validation |
| HD-32 award entity | LOCKED | 3C.8B Option A | — | Separate award tables |
| Award attributes catalog | OPEN | 3C.8B; nullable columns only | Honors/numbering/classification meaning | Soft if nullable; hard if mandatory |
| HD-38 publication | OPEN | Explicitly unresolved in 3C.8B still-open list | Publish? who? mandatory? | Classify as DEFERRED FEATURE until decided — must not invent |
| Cross-school authz | LOCKED DENY | HD-39 + RLS FORCE | — | Fail closed |
| Idempotency in-txn + fingerprint | LOCKED (arch) | 3C.13 | — | Do not weaken |
| DL-022 StudentStatus projection | LOCKED | 3C.8B DL-022 | — | Never SSOT |

```text
CONFLICT: NONE
```

## Outcome class for this phase

```text
B) Mandatory policies remain OPEN
→ IMPLEMENTATION READINESS = BLOCKED
→ Exact human decisions listed below (also 01-GATE-REPORT)
```

### Human decisions still required (mandatory for full official write path)

1. **HD-31-ROLES** — approval/issue/revoke actors + permissions (+ optional SoD)  
2. **HD-20/21-CONTENT** — eligibility/unit/threshold/exception content  
3. **SS-MULTI** / **SS-REVOKE-CLEAR** — if StudentStatus sync is in scope  
4. **HD-36-ROLES** / **HD-36-REASONS** — for production revoke  
5. **HD-38** — publish or formally DEFER out of critical path  

Optional/non-blocking for core path if deferred explicitly: award attribute catalogs, event name finalization, SoD.
